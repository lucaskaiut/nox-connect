<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\UrlSecurityValidator;
use InvalidArgumentException;
use Tests\TestCase;

class UrlSecurityValidatorTest extends TestCase
{
    private UrlSecurityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new UrlSecurityValidator;
    }

    public function test_allows_public_https_url(): void
    {
        $this->validator->assertSafe('https://example.com/webhook');

        $this->assertTrue(true);
    }

    public function test_rejects_http_scheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HTTPS');

        $this->validator->assertSafe('http://example.com/hook');
    }

    public function test_rejects_localhost(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validator->assertSafe('https://localhost/hook');
    }

    public function test_rejects_rfc1918_ipv4_literal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validator->assertSafe('https://192.168.1.1/hook');
    }

    public function test_rejects_link_local_ipv4_literal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validator->assertSafe('https://169.254.169.254/latest/meta-data');
    }

    public function test_rejects_loopback_ipv4_literal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validator->assertSafe('https://127.0.0.1/hook');
    }

    public function test_rejects_metadata_hostname(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validator->assertSafe('https://metadata.google.internal/computeMetadata/v1/');
    }

    public function test_is_blocked_ipv6_loopback(): void
    {
        $this->assertTrue($this->validator->isBlockedIp('::1'));
    }
}
