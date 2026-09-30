<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Native\Mobile\Concerns\PlatformFileOperations;
use Native\Mobile\Concerns\RunsAndroid;
use Native\Mobile\Plugins\PluginRegistry;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;

class BuildApkCommand extends Command
{
    use PlatformFileOperations, RunsAndroid;

    protected $signature = 'apk:build
        {type=debug : debug|release|bundle}
        {--output=dist : Output directory for artifacts}
        {--app-version= : Explicit versionName for release/bundle builds}
        {--no-tty : Disable TTY mode for non-interactive environments}';

    protected $description = 'Build an Android APK or AAB into dist/ without installing it';

    protected string $buildType = 'debug';

    /**
     * Release sideloads stamp version name "dev", then put the tracked version back.
     */
    protected ?string $trackedVersionRestore = null;

    public function handle(): int
    {
        $this->ensureBuildToolsOnPath();

        try {
            return $this->buildArtifact();
        } finally {
            $this->restoreTrackedVersion();
        }
    }

    protected function buildArtifact(): int
    {
        $type = strtolower((string) $this->argument('type'));

        if (! in_array($type, ['debug', 'release', 'bundle'], true)) {
            error('Type must be debug, release, or bundle.');

            return self::FAILURE;
        }

        $requestedVersion = $this->option('app-version');
        if ($requestedVersion !== null && ! preg_match('/^\d+\.\d+\.\d+$/', (string) $requestedVersion)) {
            error('Version must use semantic version format, for example 1.1.0');

            return self::FAILURE;
        }

        $this->buildType = $type;

        intro("Building Android {$type} artifact");

        if (in_array($type, ['release', 'bundle'], true)) {
            $this->ensureSigningConfig();

            $signingError = $this->signingConfigError();
            if ($signingError !== null) {
                error($signingError);
                note('Run: php artisan native:credentials android');
                note('Passwords with # or ! must be double-quoted in .env');

                return self::FAILURE;
            }

            $this->bumpReleaseVersions();
        }

        if (! $this->ensureAndroidProject()) {
            return self::FAILURE;
        }

        return match ($type) {
            'debug' => $this->buildDebugApk(),
            'release', 'bundle' => $this->buildSignedArtifact($type),
        };
    }

    protected function ensureAndroidProject(): bool
    {
        $androidPath = base_path('nativephp/android');

        if (is_dir($androidPath)) {
            return true;
        }

        note('Android project missing — running native:install android');

        $exitCode = $this->call('native:install', [
            'platform' => 'android',
            '--no-interaction' => true,
        ]);

        if ($exitCode !== self::SUCCESS || ! is_dir($androidPath)) {
            error('Failed to install the Android project. Run: php artisan native:install android');

            return false;
        }

        return true;
    }

    protected function buildDebugApk(): int
    {
        $this->androidLogPath = base_path($this->androidLogPath);
        File::ensureDirectoryExists(dirname($this->androidLogPath));
        file_put_contents($this->androidLogPath, '');
        note("Build log: {$this->androidLogPath}");

        if (! $this->validateBuildEnvironment()) {
            return self::FAILURE;
        }

        if (! $this->pluginsMatchMinSdk()) {
            return self::FAILURE;
        }

        $this->prepareAndroidBuild(cleanCache: false, excludeDevDependencies: false);

        if (! $this->compileAndroidPlugins()) {
            return self::FAILURE;
        }

        if (! $this->assembleDebugApk()) {
            return self::FAILURE;
        }

        $this->runAndroidPostBuildHooks();

        $source = base_path('nativephp/android/app/build/outputs/apk/debug/app-debug.apk');
        $destination = $this->artifactPath('stable-mixer-debug.apk');

        if (! File::exists($source)) {
            error("Debug APK not found at {$source}");

            return self::FAILURE;
        }

        $this->copyArtifact($source, $destination);
        outro("Debug APK ready: {$destination}");

        return self::SUCCESS;
    }

    protected function assembleDebugApk(): bool
    {
        $androidPath = base_path('nativephp/android');
        $gradlePath = $androidPath.DIRECTORY_SEPARATOR.'gradlew';

        if (! is_executable($gradlePath)) {
            chmod($gradlePath, 0755);
        }

        $this->components->twoColumnDetail('Build type', 'debug');
        $this->components->twoColumnDetail('App version', (string) config('nativephp.version', 'Not set'));
        $this->newLine();

        $this->logToFile('--- Starting Gradle assembleDebug ---');

        $process = Process::path($androidPath)->timeout(1200);

        if (! $this->option('no-tty')) {
            $process->tty();
        }

        $result = $process->run('./gradlew assembleDebug', function ($type, $output) {
            file_put_contents($this->androidLogPath, $output, FILE_APPEND);
        });

        if (! $result->successful()) {
            $this->logToFile('ERROR: Gradle build failed with exit code: '.$result->exitCode());
            error('Gradle build failed');
            note("Check the build log: {$this->androidLogPath}");

            return false;
        }

        $this->logToFile('Gradle assembleDebug completed successfully');

        return true;
    }

    protected function buildSignedArtifact(string $type): int
    {
        $signing = config('nativephp.android.signing', []);
        $keystore = $this->resolveKeystorePath((string) ($signing['keystore'] ?? ''));

        $this->androidLogPath = base_path($this->androidLogPath);
        File::ensureDirectoryExists(dirname($this->androidLogPath));
        $this->prepareAndroidBuild(cleanCache: false);

        $exitCode = $this->withAsyncXdgOpen(fn () => $this->call('native:package', [
            'platform' => 'android',
            '--build-type' => $type === 'bundle' ? 'bundle' : 'release',
            '--output' => $this->nativePackageOutputOption(),
            '--keystore' => $keystore,
            '--keystore-password' => (string) ($signing['keystore_password'] ?? ''),
            '--key-alias' => (string) ($signing['key_alias'] ?? ''),
            '--key-password' => (string) ($signing['key_password'] ?? ''),
            '--skip-prepare' => true,
            '--no-tty' => true,
        ]));

        if ($exitCode !== self::SUCCESS) {
            error('native:package failed');

            return self::FAILURE;
        }

        $outputDir = $this->outputDirectory();
        $extension = $type === 'bundle' ? 'aab' : 'apk';
        $packaged = $outputDir.DIRECTORY_SEPARATOR.'app-release.'.$extension;
        $destination = $this->artifactPath($this->signedArtifactFilename($extension));

        if (! File::exists($packaged)) {
            $fallback = $type === 'bundle'
                ? base_path('nativephp/android/app/build/outputs/bundle/release/app-release.aab')
                : base_path('nativephp/android/app/build/outputs/apk/release/app-release.apk');

            if (! File::exists($fallback)) {
                error("Signed {$extension} not found after packaging");

                return self::FAILURE;
            }

            $this->copyArtifact($fallback, $destination);
        } else {
            File::move($packaged, $destination);
            $this->components->twoColumnDetail('Artifact', $destination);
            $this->components->twoColumnDetail('File size', round(filesize($destination) / 1024 / 1024, 2).' MB');
        }

        outro(strtoupper($extension)." ready: {$destination}");

        return self::SUCCESS;
    }

    protected function pluginsMatchMinSdk(): bool
    {
        $minSdk = (int) config('nativephp.android.min_sdk', 26);

        if ($minSdk < 26) {
            error("NATIVEPHP_ANDROID_MIN_SDK is set to {$minSdk}, but must be at least 26.");

            return false;
        }

        foreach (app(PluginRegistry::class)->all() as $plugin) {
            $pluginMinSdk = $plugin->getAndroidMinVersion();
            if ($pluginMinSdk !== null && $minSdk < $pluginMinSdk) {
                error("Plugin '{$plugin->name}' requires Android API level {$pluginMinSdk}, but your min SDK is {$minSdk}.");

                return false;
            }
        }

        return true;
    }

    /**
     * Sideload release keeps the tracked version name and ships "dev".
     * Play bundles advance the semver. Both increment the version code.
     */
    protected function bumpReleaseVersions(): void
    {
        $currentVersion = (string) config('nativephp.version', '1.0.0');
        $currentCode = (int) config('nativephp.version_code', 1);
        $requestedVersion = $this->option('app-version');
        $requestedVersion = is_string($requestedVersion) && $requestedVersion !== '' ? $requestedVersion : null;
        $newCode = $currentCode + 1;
        $sideload = $this->buildType === 'release' && $requestedVersion === null;

        if ($sideload) {
            $newVersion = 'dev';
            $this->trackedVersionRestore = $currentVersion;
        } elseif ($requestedVersion !== null) {
            $newVersion = $requestedVersion;
        } else {
            $base = $currentVersion;
            if ($base === '' || strtoupper($base) === 'DEBUG' || strtolower($base) === 'dev') {
                $base = '1.0.0';
            }

            $parts = array_map('intval', explode('.', $base));
            while (count($parts) < 3) {
                $parts[] = 0;
            }

            $parts[2]++;
            $newVersion = $parts[0].'.'.$parts[1].'.'.$parts[2];
        }

        $this->writeEnvValue('NATIVEPHP_APP_VERSION', $newVersion);
        $this->writeEnvValue('NATIVEPHP_APP_VERSION_CODE', (string) $newCode);

        config([
            'nativephp.version' => $newVersion,
            'nativephp.version_code' => $newCode,
        ]);

        $this->components->twoColumnDetail(
            'App version',
            $sideload ? "{$currentVersion} stays tracked, APK uses dev" : "{$currentVersion} → {$newVersion}",
        );
        $this->components->twoColumnDetail('Version code', "{$currentCode} → {$newCode}");
        $this->newLine();
    }

    protected function restoreTrackedVersion(): void
    {
        if ($this->trackedVersionRestore === null) {
            return;
        }

        $restore = $this->trackedVersionRestore;
        $this->trackedVersionRestore = null;
        $this->writeEnvValue('NATIVEPHP_APP_VERSION', $restore);
        config(['nativephp.version' => $restore]);
    }

    protected function ensureSigningConfig(): void
    {
        if ($this->signingConfigError() === null) {
            return;
        }

        $password = Str::password(24, letters: true, numbers: true, symbols: false);
        $relative = 'credentials/stable-mixer-release.jks';
        $path = base_path($relative);
        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            $javaHome = getenv('JAVA_HOME') ?: '';
            $keytool = $javaHome !== '' ? $javaHome.'/bin/keytool' : 'keytool';

            $result = Process::timeout(60)->run([
                $keytool,
                '-genkeypair',
                '-keystore', $path,
                '-alias', 'stable-mixer',
                '-keyalg', 'RSA',
                '-keysize', '2048',
                '-validity', '10000',
                '-storepass', $password,
                '-keypass', $password,
                '-dname', 'CN=Stable Mixer, OU=Mobile, O=Stable Mixer, C=US',
            ]);

            if (! $result->successful()) {
                error('Could not create a release keystore.');
                $this->line($result->errorOutput() ?: $result->output());

                return;
            }

            note('Created a release keystore at credentials/stable-mixer-release.jks');
        } else {
            note('Reusing credentials/stable-mixer-release.jks. Set ANDROID_KEYSTORE_PASSWORD in .env if this file already had a password.');

            return;
        }

        $this->writeEnvValue('ANDROID_KEYSTORE_FILE', $relative);
        $this->writeEnvValue('ANDROID_KEYSTORE_PASSWORD', $password);
        $this->writeEnvValue('ANDROID_KEY_ALIAS', 'stable-mixer');
        $this->writeEnvValue('ANDROID_KEY_PASSWORD', $password);

        config([
            'nativephp.android.signing.keystore' => $relative,
            'nativephp.android.signing.keystore_password' => $password,
            'nativephp.android.signing.key_alias' => 'stable-mixer',
            'nativephp.android.signing.key_password' => $password,
        ]);
    }

    protected function signingConfigError(): ?string
    {
        $signing = config('nativephp.android.signing', []);
        $keystore = $signing['keystore'] ?? null;
        $keystorePassword = $signing['keystore_password'] ?? null;
        $keyAlias = $signing['key_alias'] ?? null;
        $keyPassword = $signing['key_password'] ?? null;

        $missing = [];
        if (! $keystore) {
            $missing[] = 'ANDROID_KEYSTORE_FILE';
        }
        if (! $keystorePassword) {
            $missing[] = 'ANDROID_KEYSTORE_PASSWORD';
        }
        if (! $keyAlias) {
            $missing[] = 'ANDROID_KEY_ALIAS';
        }
        if (! $keyPassword) {
            $missing[] = 'ANDROID_KEY_PASSWORD';
        }

        if ($missing !== []) {
            return 'Missing Android signing configuration: '.implode(', ', $missing);
        }

        if ($this->resolveKeystorePath((string) $keystore) === null) {
            return "Keystore file not found: {$keystore}";
        }

        return null;
    }

    protected function resolveKeystorePath(string $keystore): ?string
    {
        $candidates = [
            $keystore,
            base_path($keystore),
            base_path('credentials/'.basename($keystore)),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && File::exists($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }

    protected function writeEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            error('.env file not found');

            return;
        }

        $envContent = File::get($envPath);
        $line = "{$key}={$value}";

        if (preg_match("/^{$key}=.*$/m", $envContent)) {
            $envContent = preg_replace("/^{$key}=.*$/m", $line, $envContent);
        } else {
            $envContent = rtrim($envContent)."\n{$line}\n";
        }

        File::put($envPath, $envContent);
    }

    protected function outputDirectory(): string
    {
        $dir = $this->option('output') ?: 'dist';

        if (! str_starts_with($dir, '/')) {
            $dir = base_path($dir);
        }

        File::ensureDirectoryExists($dir);

        return $dir;
    }

    protected function nativePackageOutputOption(): string
    {
        $dir = (string) ($this->option('output') ?: 'dist');

        if (! str_starts_with($dir, '/')) {
            return $dir;
        }

        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (str_starts_with($dir, $base)) {
            $relative = substr($dir, strlen($base));

            return $relative !== '' ? $relative : 'dist';
        }

        return 'dist';
    }

    protected function signedArtifactFilename(string $extension): string
    {
        $code = (int) config('nativephp.version_code', 0);

        return "stable-mixer-{$code}.{$extension}";
    }

    protected function artifactPath(string $filename): string
    {
        return $this->outputDirectory().DIRECTORY_SEPARATOR.$filename;
    }

    protected function copyArtifact(string $source, string $destination): void
    {
        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);
        $this->components->twoColumnDetail('Artifact', $destination);
        $this->components->twoColumnDetail('File size', round(filesize($destination) / 1024 / 1024, 2).' MB');
    }

    protected function ensureBuildToolsOnPath(): void
    {
        $home = getenv('HOME') ?: '';
        $javaHome = getenv('JAVA_HOME') ?: '';

        if ($javaHome === '' || ! is_executable($javaHome.'/bin/java')) {
            $candidates = $home !== '' ? (glob($home.'/.local/share/mise/installs/java/*/bin/java') ?: []) : [];
            rsort($candidates);
            foreach ($candidates as $binary) {
                if (str_contains($binary, '/17.')) {
                    $javaHome = dirname(dirname($binary));
                    break;
                }
            }
            if (($javaHome === '' || ! is_executable($javaHome.'/bin/java')) && $candidates !== []) {
                $javaHome = dirname(dirname($candidates[0]));
            }
        }

        $sdk = getenv('ANDROID_HOME') ?: getenv('ANDROID_SDK_ROOT') ?: ($home !== '' ? $home.'/Android/Sdk' : '');

        $prefix = array_filter([
            $javaHome !== '' ? $javaHome.'/bin' : null,
            $sdk !== '' && is_dir($sdk.'/platform-tools') ? $sdk.'/platform-tools' : null,
        ]);

        if ($javaHome !== '' && is_executable($javaHome.'/bin/java')) {
            putenv('JAVA_HOME='.$javaHome);
            $_ENV['JAVA_HOME'] = $javaHome;
        }

        if ($sdk !== '' && is_dir($sdk)) {
            putenv('ANDROID_HOME='.$sdk);
            putenv('ANDROID_SDK_ROOT='.$sdk);
            $_ENV['ANDROID_HOME'] = $sdk;
            $_ENV['ANDROID_SDK_ROOT'] = $sdk;
        }

        if ($prefix === []) {
            return;
        }

        $path = implode(':', $prefix).':'.(getenv('PATH') ?: '/usr/bin:/bin');
        putenv('PATH='.$path);
        $_ENV['PATH'] = $path;
    }

    /**
     * native:package runs xdg-open and waits for it. Background that call.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withAsyncXdgOpen(callable $callback): mixed
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return $callback();
        }

        $real = trim((string) shell_exec('command -v xdg-open 2>/dev/null'));
        if ($real === '' || ! is_executable($real)) {
            return $callback();
        }

        $shimDir = sys_get_temp_dir().'/stable-mixer-async-xdg-open';
        File::ensureDirectoryExists($shimDir);
        $shim = $shimDir.'/xdg-open';
        File::put($shim, "#!/bin/sh\n".escapeshellarg($real)." \"$@\" >/dev/null 2>&1 &\n");
        chmod($shim, 0755);

        $previous = getenv('PATH') ?: '/usr/bin:/bin';
        putenv('PATH='.$shimDir.':'.$previous);
        $_ENV['PATH'] = $shimDir.':'.$previous;

        try {
            return $callback();
        } finally {
            putenv('PATH='.$previous);
            $_ENV['PATH'] = $previous;
        }
    }
}
