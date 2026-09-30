<?php

namespace Tests\Unit;

use App\Support\TenantCode;
use PHPUnit\Framework\TestCase;

class TenantCodeTest extends TestCase
{
    public function test_it_accepts_an_eight_character_code(): void
    {
        $this->assertSame('AbC12xYZ', TenantCode::fromScan('AbC12xYZ'));
    }

    public function test_it_reads_a_code_from_a_qr_payload(): void
    {
        $this->assertSame('AbC12xYZ', TenantCode::fromScan('https://stable.test/join/AbC12xYZ'));
        $this->assertSame('AbC12xYZ', TenantCode::fromScan('https://stable.test/start?tenant=AbC12xYZ'));
        $this->assertSame('AbC12xYZ', TenantCode::fromScan('tenant:AbC12xYZ'));
    }

    public function test_it_rejects_anything_else(): void
    {
        $this->assertNull(TenantCode::fromScan('short'));
        $this->assertNull(TenantCode::fromScan('ABCD12345'));
        $this->assertNull(TenantCode::fromScan('ABCD-123'));
        $this->assertNull(TenantCode::fromScan(''));
    }
}
