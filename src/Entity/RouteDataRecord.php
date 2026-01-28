<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;

/**
 * Route access record entity.
 *
 * Stores individual access records for routes with timestamp,
 * HTTP status code, and response time.
 * This table is used for temporal analysis of route access patterns.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[ORM\Entity(repositoryClass: RouteDataRecordRepository::class)]
#[ORM\Table(name: 'routes_data_records')]
#[ORM\Index(columns: ['route_data_id'], name: 'idx_record_route_data_id')]
#[ORM\Index(columns: ['accessed_at'], name: 'idx_record_accessed_at')]
#[ORM\Index(columns: ['status_code'], name: 'idx_record_status_code')]
#[ORM\Index(columns: ['route_data_id', 'accessed_at'], name: 'idx_record_route_accessed')]
class RouteDataRecord
{
    /**
     * Primary key identifier.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Related route data entity.
     */
    #[ORM\ManyToOne(targetEntity: RouteData::class, inversedBy: 'accessRecords')]
    #[ORM\JoinColumn(name: 'route_data_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?RouteData $routeData = null;

    /**
     * Access timestamp.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $accessedAt;

    /**
     * HTTP status code of the response.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $statusCode = null;

    /**
     * Response time in seconds.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $responseTime = null;

    /**
     * Total number of database queries for this request.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $totalQueries = null;

    /**
     * Total query execution time in seconds for this request.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $queryTime = null;

    /**
     * Peak memory usage in bytes for this request.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $memoryUsage = null;

    /**
     * Creates a new instance.
     */
    public function __construct()
    {
        $this->accessedAt = new \DateTimeImmutable();
    }

    /**
     * Get the ID.
     *
     * @return int|null The entity ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the related route data.
     *
     * @return RouteData|null The route data entity
     */
    public function getRouteData(): ?RouteData
    {
        return $this->routeData;
    }

    /**
     * Set the related route data.
     *
     * @param RouteData|null $routeData The route data entity
     */
    public function setRouteData(?RouteData $routeData): self
    {
        $this->routeData = $routeData;

        return $this;
    }

    /**
     * Get the access timestamp.
     *
     * @return \DateTimeImmutable The access timestamp
     */
    public function getAccessedAt(): \DateTimeImmutable
    {
        return $this->accessedAt;
    }

    /**
     * Set the access timestamp.
     *
     * @param \DateTimeImmutable $accessedAt The access timestamp
     */
    public function setAccessedAt(\DateTimeImmutable $accessedAt): self
    {
        $this->accessedAt = $accessedAt;

        return $this;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int|null The HTTP status code
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Set the HTTP status code.
     *
     * @param int|null $statusCode The HTTP status code
     */
    public function setStatusCode(?int $statusCode): self
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Get the response time.
     *
     * @return float|null The response time in seconds
     */
    public function getResponseTime(): ?float
    {
        return $this->responseTime;
    }

    /**
     * Set the response time.
     *
     * @param float|null $responseTime The response time in seconds
     */
    public function setResponseTime(?float $responseTime): self
    {
        $this->responseTime = $responseTime;

        return $this;
    }

    /**
     * Get the total number of database queries.
     *
     * @return int|null Total queries for this request
     */
    public function getTotalQueries(): ?int
    {
        return $this->totalQueries;
    }

    /**
     * Set the total number of database queries.
     *
     * @param int|null $totalQueries Total queries for this request
     */
    public function setTotalQueries(?int $totalQueries): self
    {
        $this->totalQueries = $totalQueries;

        return $this;
    }

    /**
     * Get the total query execution time in seconds.
     *
     * @return float|null Query time for this request
     */
    public function getQueryTime(): ?float
    {
        return $this->queryTime;
    }

    /**
     * Set the total query execution time in seconds.
     *
     * @param float|null $queryTime Query time for this request
     */
    public function setQueryTime(?float $queryTime): self
    {
        $this->queryTime = $queryTime;

        return $this;
    }

    /**
     * Get the peak memory usage in bytes.
     *
     * @return int|null Memory usage for this request
     */
    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    /**
     * Set the peak memory usage in bytes.
     *
     * @param int|null $memoryUsage Memory usage for this request
     */
    public function setMemoryUsage(?int $memoryUsage): self
    {
        $this->memoryUsage = $memoryUsage;

        return $this;
    }
}
