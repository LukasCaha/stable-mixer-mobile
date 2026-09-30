<?php

namespace App\Support;

class M4aDuration
{
    /**
     * Length in whole seconds from the MP4 mvhd atom, or null when the file
     * has no readable movie header.
     */
    public static function seconds(string $path): ?int
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $size = filesize($path);
            if ($size === false || $size < 16) {
                return null;
            }

            $times = self::scan($handle, 0, $size);
            if ($times === null || $times['timescale'] <= 0) {
                return null;
            }

            return (int) round($times['duration'] / $times['timescale']);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return array{timescale: int, duration: int}|null
     */
    private static function scan($handle, int $start, int $end): ?array
    {
        $offset = $start;

        while ($offset + 8 <= $end) {
            fseek($handle, $offset);
            $header = fread($handle, 8);
            if ($header === false || strlen($header) < 8) {
                return null;
            }

            $size = unpack('N', substr($header, 0, 4))[1];
            $type = substr($header, 4, 4);
            $headerSize = 8;

            if ($size === 1) {
                $large = fread($handle, 8);
                if ($large === false || strlen($large) < 8) {
                    return null;
                }
                $hi = unpack('N', substr($large, 0, 4))[1];
                $lo = unpack('N', substr($large, 4, 4))[1];
                $size = ($hi << 32) + $lo;
                $headerSize = 16;
            } elseif ($size === 0) {
                $size = $end - $offset;
            }

            if ($size < $headerSize) {
                return null;
            }

            $bodyStart = $offset + $headerSize;
            $bodyEnd = $offset + $size;

            if ($type === 'mvhd') {
                return self::readMvhd($handle, $bodyStart);
            }

            if (in_array($type, ['moov', 'trak', 'mdia'], true)) {
                $found = self::scan($handle, $bodyStart, min($bodyEnd, $end));
                if ($found !== null) {
                    return $found;
                }
            }

            $offset += $size;
        }

        return null;
    }

    /**
     * @param  resource  $handle
     * @return array{timescale: int, duration: int}|null
     */
    private static function readMvhd($handle, int $start): ?array
    {
        fseek($handle, $start);
        $version = fread($handle, 1);
        if ($version === false || $version === '') {
            return null;
        }

        if (ord($version) === 1) {
            fseek($handle, $start + 20);
            $timescale = self::uint32($handle);
            $duration = self::uint64($handle);
        } else {
            fseek($handle, $start + 12);
            $timescale = self::uint32($handle);
            $duration = self::uint32($handle);
        }

        if ($timescale === null || $duration === null) {
            return null;
        }

        return ['timescale' => $timescale, 'duration' => $duration];
    }

    /**
     * @param  resource  $handle
     */
    private static function uint32($handle): ?int
    {
        $bytes = fread($handle, 4);

        return $bytes !== false && strlen($bytes) === 4 ? unpack('N', $bytes)[1] : null;
    }

    /**
     * @param  resource  $handle
     */
    private static function uint64($handle): ?int
    {
        $bytes = fread($handle, 8);
        if ($bytes === false || strlen($bytes) !== 8) {
            return null;
        }

        $hi = unpack('N', substr($bytes, 0, 4))[1];
        $lo = unpack('N', substr($bytes, 4, 4))[1];

        return ($hi << 32) + $lo;
    }
}
