<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Helper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Name;
use Doctrine\DBAL\Types\StringType;
use Nowo\PerformanceBundle\Helper\DbalSchemaNameHelper;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Covers the full fallback chain in DbalSchemaNameHelper:
 *   1. getObjectName() → Name (happy path, already partially covered)
 *   2. getObjectName() throws Throwable → falls through to getName()
 *   3. getName() fallback
 *   4. ReflectionClass fallback (no getName, no getObjectName)
 *   5. getQuotedName() all branches
 */
final class DbalSchemaNameHelperTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // getLogicalName
    // ---------------------------------------------------------------------------

    public function testGetLogicalNameWithRealColumn(): void
    {
        $column = new Column('my_column', new StringType());
        $this->assertSame('my_column', DbalSchemaNameHelper::getLogicalName($column));
    }

    /** Covers catch (Throwable) in getLogicalName when getObjectName() throws. */
    public function testGetLogicalNameWhenGetObjectNameThrows(): void
    {
        $asset = new class {
            public function getObjectName(): never
            {
                throw new RuntimeException('name not initialized');
            }

            public function getName(): string
            {
                return 'fallback_name';
            }
        };

        $this->assertSame('fallback_name', DbalSchemaNameHelper::getLogicalName($asset));
    }

    /** Covers getName() fallback (no getObjectName). */
    public function testGetLogicalNameWithGetNameFallback(): void
    {
        $asset = new class {
            public function getName(): string
            {
                return 'table_name';
            }
        };

        $this->assertSame('table_name', DbalSchemaNameHelper::getLogicalName($asset));
    }

    /** Covers getName() fallback when name is non-string. */
    public function testGetLogicalNameWithGetNameNonString(): void
    {
        $asset = new class {
            public function getName(): int
            {
                return 42;
            }
        };

        $this->assertSame('42', DbalSchemaNameHelper::getLogicalName($asset));
    }

    /** Covers reflection fallback with string name property (lines 57-64). */
    public function testGetLogicalNameWithReflectionStringProperty(): void
    {
        // No getObjectName(), no getName() — falls through to reflection.
        $asset = new class {
            private string $name = 'reflected_table';
        };

        $this->assertSame('reflected_table', DbalSchemaNameHelper::getLogicalName($asset));
    }

    /** Covers reflection fallback when name property holds a Name instance (lines 60-61). */
    public function testGetLogicalNameWithReflectionNameProperty(): void
    {
        $column  = new Column('real_col', new StringType());
        $nameObj = $column->getObjectName(); // Doctrine\DBAL\Schema\Name instance

        $asset = new class($nameObj) {
            public function __construct(private Name $name)
            {
            }
        };

        $this->assertSame('real_col', DbalSchemaNameHelper::getLogicalName($asset));
    }

    /** Covers reflection catch(Exception) → return '' when property does not exist (lines 65-66). */
    public function testGetLogicalNameWithNoNameFallbackReturnsEmpty(): void
    {
        // stdClass has no properties — ReflectionClass::getProperty('name') throws.
        $this->assertSame('', DbalSchemaNameHelper::getLogicalName(new stdClass()));
    }

    /** Covers getLogicalName trimming backticks. */
    public function testGetLogicalNameTrimsQuotes(): void
    {
        $asset = new class {
            public function getObjectName(): never
            {
                throw new RuntimeException('not init');
            }

            public function getName(): string
            {
                return '`quoted_name`';
            }
        };

        $this->assertSame('quoted_name', DbalSchemaNameHelper::getLogicalName($asset));
    }

    // ---------------------------------------------------------------------------
    // getQuotedName
    // ---------------------------------------------------------------------------

    /** Covers getQuotedName with a real Column and Platform (lines 75,77,79,81,82,83). */
    public function testGetQuotedNameWithRealColumnAndPlatform(): void
    {
        $column   = new Column('my_col', new StringType());
        $platform = new MySQL80Platform();

        $quoted = DbalSchemaNameHelper::getQuotedName($column, $platform);

        $this->assertStringContainsString('my_col', $quoted);
    }

    /** Covers getQuotedName platform extraction via Connection (lines 75-76). */
    public function testGetQuotedNameWithConnectionExtractsPlatform(): void
    {
        $platform   = new MySQL80Platform();
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $column = new Column('conn_col', new StringType());
        $quoted = DbalSchemaNameHelper::getQuotedName($column, $connection);

        $this->assertStringContainsString('conn_col', $quoted);
    }

    /** Covers getQuotedName when getObjectName() throws → falls to getQuotedName() (lines 85,90,91). */
    public function testGetQuotedNameWhenGetObjectNameThrowsFallsToGetQuotedName(): void
    {
        $platform = new MySQL80Platform();

        $asset = new class($platform) {
            public function getObjectName(): never
            {
                throw new RuntimeException('name not init');
            }

            public function getQuotedName(AbstractPlatform $platform): string
            {
                return '`asset_name`';
            }
        };

        $result = DbalSchemaNameHelper::getQuotedName($asset, $platform);
        $this->assertSame('`asset_name`', $result);
    }

    /** Covers getQuotedName fallback to getLogicalName when no getQuotedName (line 94). */
    public function testGetQuotedNameFallsBackToGetLogicalName(): void
    {
        $platform = new MySQL80Platform();

        $asset = new class {
            public function getName(): string
            {
                return 'logical_name';
            }
        };

        $result = DbalSchemaNameHelper::getQuotedName($asset, $platform);
        $this->assertSame('logical_name', $result);
    }

    /** Covers getQuotedName with no getObjectName and asset has getQuotedName (lines 79,90,91). */
    public function testGetQuotedNameWithNoGetObjectNameAndHasGetQuotedName(): void
    {
        $platform = new MySQL80Platform();

        $asset = new class {
            public function getQuotedName(AbstractPlatform $platform): string
            {
                return 'quoted_asset';
            }
        };

        $result = DbalSchemaNameHelper::getQuotedName($asset, $platform);
        $this->assertSame('quoted_asset', $result);
    }
}
