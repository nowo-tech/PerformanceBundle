<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiler pass to configure the table name for RouteData entity.
 * 
 * This pass stores the table name configuration so it can be used
 * by Doctrine event listeners to modify the entity metadata.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
class TableNamePass implements CompilerPassInterface
{
    /**
     * Process the container builder.
     *
     * This compiler pass is currently a placeholder. The table name
     * is configured via the TableNameSubscriber Doctrine event listener
     * which modifies the entity metadata at runtime.
     *
     * @param ContainerBuilder $container The container builder
     * @return void
     */
    public function process(ContainerBuilder $container): void
    {
        // The table name is configured via the entity attribute
        // Users can extend the entity class to override the table name
        // or we can use a Doctrine event listener to modify metadata at runtime
    }
}
