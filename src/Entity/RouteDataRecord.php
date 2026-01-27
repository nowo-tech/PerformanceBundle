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
 * @copyright 2025 Nowo.tech
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
     *
     * @var int|null
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Related route data entity.
     *
     * @var RouteData|null
     */
    #[ORM\ManyToOne(targetEntity: RouteData::class, inversedBy: 'accessRecords')]
    #[ORM\JoinColumn(name: 'route_data_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?RouteData $routeData = null;

    /**
     * Access timestamp.
     *
     * @var \DateTimeImmutable
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $accessedAt;

    /**
     * HTTP status code of the response.
     *
     * @var int|null
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $statusCode = null;

    /**
     * Response time in seconds.
     *
     * @var float|null
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $responseTime = null;

    /**
     * Constructor.
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
     * @return self
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
     * @return self
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
     * @return self
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
     * @return self
     */
    public function setResponseTime(?float $responseTime): self
    {
        $this->responseTime = $responseTime;

        return $this;
    }
}
