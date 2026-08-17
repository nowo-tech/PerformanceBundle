<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Integration;

/**
 * No-op replacement for DoctrineDbalCacheAdapterSchemaListener.
 *
 * That listener calls GenerateSchemaEventArgs::setSchema(), which requires DBAL
 * Schema::edit() (doctrine/dbal ^4.5). Latest released DBAL is 4.4.x.
 */
final class NoopDoctrineCacheSchemaListener
{
    public function postGenerateSchema(): void
    {
    }
}
