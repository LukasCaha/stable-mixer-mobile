# Stable Mixer companion

Android app for recording a voice memo and queueing it for the transcription server. The phone does not transcribe anything.

One screen:

- First launch asks for an 8-character pairing code (`A–Z`, `a–z`, `0–9`). Scan it when the paid QR plugin is installed, or type it. The phone checks the code with the server and shows the stable name when it connects.
- The red button records, pauses, and resumes. Stop writes an `.m4a` on the phone and shows it as pending sync.
- While the app is open it retries the upload with backoff. The pending count is the memos that are not synced yet.

## What you need on this machine

- PHP 8.3+ with `pdo_sqlite` enabled (`/etc/php/conf.d` does not load it on this machine until you add `extension=pdo_sqlite` and `extension=sqlite3`)
- Composer
- JDK 17 (`java -version`)
- Android Studio with SDK API 33+, platform-tools, and `adb` on your `PATH`
- `ANDROID_HOME` pointing at the SDK (often `~/Android/Sdk`)

This checkout does not include the Android SDK, so the APK has to be built on your machine.

## Build an APK

```bash
composer apk:release
```

That writes a signed sideload APK to `dist/`. The first run creates `credentials/stable-mixer-release.jks` and stores the passwords in `.env`. A debug build is `composer apk:debug`. A Play Store bundle is `composer apk:bundle`.

Install a release APK with:

```bash
adb install -r dist/stable-mixer-*.apk
```

If Android says the signatures do not match, uninstall the old debug build first. `native:install` runs automatically when `nativephp/android` is missing.

The app talks to `https://stable.on-forge.com` unless you set `STT_BASE_URL`. Pairing calls `GET {STT_BASE_URL}/api/v1/stables/{code}`. Uploads go to `POST {STT_BASE_URL}/api/v1/memos` with header `X-Tenant` and multipart field `file` (`audio/m4a`). A success body is `{ "id": "...", "status": "queued" }`.

Set `STT_UPLOAD_URL` only when the upload address is not that default path. Rebuild after changing either value:

```bash
# emulator talks to your computer as 10.0.2.2
STT_BASE_URL=http://10.0.2.2:8000

# a real phone on the same Wi-Fi uses your computer's LAN address
STT_BASE_URL=http://192.168.1.20:8000
```

## QR scanner plugin

The scanner is the paid `nativephp/mobile-scanner` package. It is not installed in this repo until you add your marketplace login:

```bash
composer config repositories.nativephp-plugins composer https://plugins.nativephp.com
composer config http-basic.plugins.nativephp.com you@example.com your-license-key
composer require nativephp/mobile-scanner
php artisan native:plugin:register nativephp/mobile-scanner
```

Then run `php artisan native:run android --build=debug` again so the camera scanner is compiled in. Until then, type the 8-character code. The app accepts a bare code, `tenant:ABCD1234`, `?tenant=ABCD1234`, or a URL whose last path segment is the code. The demo button uses `DEMO1234` and only connects when that stable exists on the server.

## Tests

```bash
php artisan test
```

## Out of scope

Playback, transcripts, accounts, and on-device speech-to-text. Background recording while the screen is locked stays off.
