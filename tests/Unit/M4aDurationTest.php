<?php

namespace Tests\Unit;

use App\Support\M4aDuration;
use PHPUnit\Framework\TestCase;

class M4aDurationTest extends TestCase
{
    public function test_it_reads_seconds_from_the_movie_header(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'm4a');
        $mvhd = pack('N', 0).pack('N', 0).pack('N', 0).pack('N', 1000).pack('N', 90000);
        $mvhdBox = pack('N', 8 + strlen($mvhd)).'mvhd'.$mvhd;
        $moov = pack('N', 8 + strlen($mvhdBox)).'moov'.$mvhdBox;
        file_put_contents($path, $moov);

        try {
            $this->assertSame(90, M4aDuration::seconds($path));
        } finally {
            unlink($path);
        }
    }

    public function test_it_returns_null_for_a_file_that_is_not_audio(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'txt');
        file_put_contents($path, 'not-a-real-m4a');

        try {
            $this->assertNull(M4aDuration::seconds($path));
        } finally {
            unlink($path);
        }
    }
}
