<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Tests\Support;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Nowo\PerformanceBundle\Service\MetricsEntityManagerFactory;
use Throwable;

use function array_shift;

/**
 * A private-manager factory that plays a script, one step per created manager (one per write attempt): before handing
 * out the manager it runs the step's "meanwhile" callback (another worker committing a row), and its manager's flush
 * runs the step's "on flush" callback (inject a competing write on a second connection, or throw a failure).
 */
final class ScriptedMetricsEntityManagerFactory extends MetricsEntityManagerFactory
{
    public int $created = 0;

    /**
     * @param list<array{meanwhile?: callable(Connection): void, onFlush?: callable(Connection): void, throw?: Throwable}> $steps
     */
    public function __construct(private array $steps)
    {
    }

    public function create(EntityManagerInterface $source): EntityManagerInterface
    {
        ++$this->created;
        $step       = array_shift($this->steps) ?? [];
        $connection = $source->getConnection();
        if (isset($step['meanwhile'])) {
            ($step['meanwhile'])($connection);
        }

        $events = new EventManager();
        if (isset($step['onFlush']) || isset($step['throw'])) {
            $events->addEventListener(Events::onFlush, new class($step, $connection) {
                /** @param array{onFlush?: callable(Connection): void, throw?: Throwable} $step */
                public function __construct(private readonly array $step, private readonly Connection $connection)
                {
                }

                public function onFlush(): void
                {
                    if (isset($this->step['onFlush'])) {
                        ($this->step['onFlush'])($this->connection);
                    }
                    if (isset($this->step['throw'])) {
                        throw $this->step['throw'];
                    }
                }
            });
        }

        return new EntityManager($connection, $source->getConfiguration(), $events);
    }
}
