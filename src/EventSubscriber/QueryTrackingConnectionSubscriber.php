<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddleware;
use Nowo\PerformanceBundle\DBAL\QueryTrackingMiddlewareRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber that applies QueryTrackingMiddleware to Doctrine connections.
 *
 * This subscriber intercepts connection creation and applies the middleware
 * to track all database queries automatically.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
class QueryTrackingConnectionSubscriber implements EventSubscriberInterface
{
    /**
     * Manager registry to get connections.
     */
    private readonly ManagerRegistry $registry;

    /**
     * Connection name to track.
     */
    private readonly string $connectionName;

    /**
     * Whether query tracking is enabled.
     */
    private readonly bool $trackQueries;

    /**
     * Whether the bundle is enabled.
     */
    private readonly bool $enabled;

    /**
     * Tracked connections to avoid re-wrapping.
     *
     * @var array<string, bool>
     */
    private array $trackedConnections = [];

    /**
     * Constructor.
     *
     * @param ManagerRegistry $registry The Doctrine registry
     * @param bool $enabled Whether the bundle is enabled
     * @param bool $trackQueries Whether query tracking is enabled
     * @param string $connectionName The connection name to track
     */
    public function __construct(
        ManagerRegistry $registry,
        #[Autowire('%nowo_performance.enabled%')]
        bool $enabled,
        #[Autowire('%nowo_performance.track_queries%')]
        bool $trackQueries,
        #[Autowire('%nowo_performance.connection%')]
        string $connectionName
    ) {
        $this->registry = $registry;
        $this->enabled = $enabled;
        $this->trackQueries = $trackQueries;
        $this->connectionName = $connectionName;
    }

    /**
     * Apply middleware to connections on kernel request.
     *
     * @param KernelEvent $event The kernel event
     * @return void
     */
    public function onKernelRequest(KernelEvent $event): void
    {
        if (!$this->enabled || !$this->trackQueries) {
            return;
        }

        // Apply middleware to the connection FIRST
        // Try multiple times in case connection isn't ready yet
        $maxAttempts = 3;
        $attempt = 0;
        while ($attempt < $maxAttempts && !isset($this->trackedConnections[$this->connectionName])) {
            $this->applyMiddlewareToConnection();
            $attempt++;
            
            // Small delay to allow connection to be ready
            if ($attempt < $maxAttempts && !isset($this->trackedConnections[$this->connectionName])) {
                usleep(10000); // 10ms delay
            }
        }
        
        // Reset query tracking AFTER middleware is applied
        // This ensures queries executed after this point are tracked
        QueryTrackingMiddleware::reset();
    }

    /**
     * Apply middleware to the configured connection.
     *
     * Uses QueryTrackingMiddlewareRegistry to apply the middleware
     * using the appropriate method for the Doctrine version.
     *
     * @return void
     */
    private function applyMiddlewareToConnection(): void
    {
        $connectionKey = $this->connectionName;
        
        // Avoid re-wrapping if already tracked
        if (isset($this->trackedConnections[$connectionKey])) {
            return;
        }

        try {
            $middleware = new QueryTrackingMiddleware();
            
            // Use the registry to apply middleware with version detection
            $success = QueryTrackingMiddlewareRegistry::applyMiddleware(
                $this->registry,
                $this->connectionName,
                $middleware
            );
            
            if ($success) {
                $this->trackedConnections[$connectionKey] = true;
            } else {
                // If reflection failed, reset the tracking flag to try again next request
                // This handles cases where the connection isn't ready yet
                unset($this->trackedConnections[$connectionKey]);
            }
        } catch (\Exception $e) {
            // Reset tracking flag on error to retry next request
            unset($this->trackedConnections[$connectionKey]);
        }
    }
}
