<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Unit\DependencyInjection\Compiler;

use Nowo\PerformanceBundle\DependencyInjection\Compiler\TableNamePass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TableNamePassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_performance.table_name', 'custom_table');
        
        $pass = new TableNamePass();
        
        // Should not throw any exception
        $pass->process($container);
        
        $this->assertTrue(true);
    }

    public function testProcessWithoutParameter(): void
    {
        $container = new ContainerBuilder();
        
        $pass = new TableNamePass();
        
        // Should not throw any exception even without parameter
        $pass->process($container);
        
        $this->assertTrue(true);
    }
}
