<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\Command\CreateTableCommand;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests for helper methods that handle DBAL 2.x and 3.x compatibility.
 */
final class CreateTableCommandHelperMethodsTest extends TestCase
{
    private ManagerRegistry|MockObject $registry;
    private Connection|MockObject $connection;
    private AbstractPlatform|MockObject $platform;
    private CreateTableCommand $command;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->connection = $this->createMock(Connection::class);
        $this->platform = $this->createMock(AbstractPlatform::class);

        $this->registry->method('getConnection')->with('default')->willReturn($this->connection);
        $this->connection->method('getDatabasePlatform')->willReturn($this->platform);

        $this->command = new CreateTableCommand(
            $this->registry,
            'default',
            'routes_data'
        );
    }

    /**
     * Test quoteIdentifier with DBAL 3.x (quoteSingleIdentifier).
     */
    public function testQuoteIdentifierUsesQuoteSingleIdentifierForDBAL3x(): void
    {
        // Mock platform with quoteSingleIdentifier (DBAL 3.x)
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('quoteSingleIdentifier')
            ->with('table_name')
            ->willReturn('`table_name`');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $platform, 'table_name');
        $this->assertSame('`table_name`', $result);
    }

    /**
     * Test quoteIdentifier with DBAL 2.x (quoteIdentifier).
     */
    public function testQuoteIdentifierFallsBackToQuoteIdentifierForDBAL2x(): void
    {
        // Mock platform without quoteSingleIdentifier (DBAL 2.x)
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('quoteIdentifier')
            ->with('table_name')
            ->willReturn('`table_name`');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $platform, 'table_name');
        $this->assertSame('`table_name`', $result);
    }

    /**
     * Test getColumnName with DBAL 3.x (getQuotedName).
     */
    public function testGetColumnNameUsesGetQuotedNameForDBAL3x(): void
    {
        $column = $this->createMock(Column::class);
        $column->method('getQuotedName')
            ->with($this->platform)
            ->willReturn('`name`');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getColumnName');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $column, $this->connection);
        $this->assertSame('`name`', $result);
    }

    /**
     * Test getColumnName with DBAL 2.x (getName).
     */
    public function testGetColumnNameFallsBackToGetNameForDBAL2x(): void
    {
        $column = $this->createMock(Column::class);
        // getQuotedName doesn't exist in DBAL 2.x
        if (method_exists($column, 'getQuotedName')) {
            $column->method('getQuotedName')
                ->willThrowException(new \BadMethodCallException('Method not available'));
        }
        $column->method('getName')->willReturn('name');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getColumnName');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $column, $this->connection);
        // In DBAL 2.x, getName() returns a Name object, but we convert it to string
        $this->assertIsString($result);
    }

    /**
     * Test getAssetName with DBAL 3.x (getQuotedName) for Index.
     */
    public function testGetAssetNameUsesGetQuotedNameForDBAL3x(): void
    {
        $index = $this->createMock(Index::class);
        $index->method('getQuotedName')
            ->with($this->platform)
            ->willReturn('`idx_name`');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getAssetName');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $index, $this->connection);
        $this->assertSame('`idx_name`', $result);
    }

    /**
     * Test getAssetName with DBAL 2.x (getName) for Index.
     */
    public function testGetAssetNameFallsBackToGetNameForDBAL2x(): void
    {
        $index = $this->createMock(Index::class);
        // getQuotedName doesn't exist in DBAL 2.x
        if (method_exists($index, 'getQuotedName')) {
            $index->method('getQuotedName')
                ->willThrowException(new \BadMethodCallException('Method not available'));
        }
        $index->method('getName')->willReturn('idx_name');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getAssetName');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $index, $this->connection);
        // In DBAL 2.x, getName() returns a Name object, but we convert it to string
        $this->assertIsString($result);
    }

    /**
     * Test that helper methods handle Name objects correctly.
     */
    public function testHelperMethodsHandleNameObjects(): void
    {
        // Create a mock Name object (DBAL 2.x style)
        $nameObject = $this->createMock(\Doctrine\DBAL\Schema\Identifier::class);
        $nameObject->method('__toString')->willReturn('table_name');

        $column = $this->createMock(Column::class);
        if (method_exists($column, 'getQuotedName')) {
            $column->method('getQuotedName')
                ->willThrowException(new \BadMethodCallException());
        }
        $column->method('getName')->willReturn($nameObject);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getColumnName');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $column, $this->connection);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
