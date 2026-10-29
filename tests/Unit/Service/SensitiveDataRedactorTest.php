<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Service;

use Nowo\PerformanceBundle\Service\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

final class SensitiveDataRedactorTest extends TestCase
{
    public function testRedactsSensitiveQueryValuesAndKeepsTheRest(): void
    {
        self::assertSame(
            '/reset?token=%5Bredacted%5D&lang=es',
            SensitiveDataRedactor::redactUrl('/reset?token=abc123&lang=es'),
        );
        self::assertSame(
            'https://idp.example.com/cb?code=%5Bredacted%5D&state=%5Bredacted%5D&page=2',
            SensitiveDataRedactor::redactUrl('https://idp.example.com/cb?code=xyz&state=s1&page=2#frag'),
        );
        self::assertSame('/plain/path', SensitiveDataRedactor::redactUrl('/plain/path'));
        self::assertSame('/x', SensitiveDataRedactor::redactUrl('/x?'));
    }

    public function testRedactsNestedParamsAndCompoundKeys(): void
    {
        self::assertSame(
            ['id' => 5, 'reset_token' => '[redacted]', 'filter' => ['api_key' => '[redacted]', 'q' => 'monkey'], 'monkey' => 'kept'],
            SensitiveDataRedactor::redactParams(['id' => 5, 'reset_token' => 't', 'filter' => ['api_key' => 'k', 'q' => 'monkey'], 'monkey' => 'kept']),
        );
    }

    public function testSensitiveKeyMatching(): void
    {
        self::assertTrue(SensitiveDataRedactor::isSensitiveKey('Signature'));
        self::assertTrue(SensitiveDataRedactor::isSensitiveKey('X-Amz-Signature'));
        self::assertTrue(SensitiveDataRedactor::isSensitiveKey('_hash'));
        self::assertFalse(SensitiveDataRedactor::isSensitiveKey('keyword'));
        self::assertFalse(SensitiveDataRedactor::isSensitiveKey('page'));
    }
}
