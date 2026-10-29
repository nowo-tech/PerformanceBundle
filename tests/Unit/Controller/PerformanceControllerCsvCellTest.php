<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Controller;

use Nowo\PerformanceBundle\Controller\PerformanceController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * CSV exports neutralise spreadsheet formulas in attacker-controlled cells (referer, user identifier).
 */
final class PerformanceControllerCsvCellTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, mixed}>
     */
    public static function cells(): iterable
    {
        yield 'formula' => ['=HYPERLINK("http://evil.test")', '\'=HYPERLINK("http://evil.test")'];
        yield 'plus' => ['+1+1', "'+1+1"];
        yield 'minus' => ['-2', "'-2"];
        yield 'at' => ['@SUM(A1)', "'@SUM(A1)"];
        yield 'tab' => ["\t=1", "'\t=1"];
        yield 'carriage return' => ["\r=1", "'\r=1"];
        yield 'plain text' => ['https://example.com/', 'https://example.com/'];
        yield 'empty' => ['', ''];
        yield 'number' => [-3.5, -3.5];
        yield 'null' => [null, null];
    }

    #[DataProvider('cells')]
    public function testCsvCell(mixed $value, mixed $expected): void
    {
        self::assertSame($expected, (new ReflectionMethod(PerformanceController::class, 'csvCell'))->invoke(null, $value));
    }
}
