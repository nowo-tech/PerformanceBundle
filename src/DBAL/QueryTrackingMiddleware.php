<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\DBAL;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * DBAL Middleware for tracking database queries.
 *
 * Counters live on {@see QueryTrackingCounters} (shared DI service) so FrankenPHP
 * workers do not leak mutable static state across requests.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class QueryTrackingMiddleware implements Middleware
{
    public function __construct(
        private readonly QueryTrackingCounters $counters = new QueryTrackingCounters(),
    ) {
    }

    public function getCounters(): QueryTrackingCounters
    {
        return $this->counters;
    }

    public function wrap(Driver $driver): Driver
    {
        $counters = $this->counters;

        return new class($driver, $counters) extends AbstractDriverMiddleware {
            public function __construct(
                Driver $driver,
                private readonly QueryTrackingCounters $counters,
            ) {
                parent::__construct($driver);
            }

            public function connect(array $params): Connection
            {
                return new QueryTrackingConnection(
                    parent::connect($params),
                    $this->counters,
                );
            }
        };
    }
}
