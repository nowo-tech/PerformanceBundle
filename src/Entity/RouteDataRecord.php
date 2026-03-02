<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PerformanceBundle\Repository\RouteDataRecordRepository;

use function strlen;

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
#[ORM\UniqueConstraint(name: 'uniq_record_request_id', columns: ['request_id'])]
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
    private DateTimeImmutable $accessedAt;

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
     * Unique request identifier to avoid duplicate records for the same HTTP request
     * (e.g. when main request and sub-requests both fire TERMINATE).
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, unique: true)]
    private ?string $requestId = null;

    /**
     * HTTP Referer header (page that linked to this request).
     */
    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $referer = null;

    /**
     * Logged-in user identifier (e.g. username, email from UserInterface::getUserIdentifier()).
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $userIdentifier = null;

    /**
     * Logged-in user ID (stringified, from User entity getId() if present).
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $userId = null;

    /**
     * Route parameters for this specific request (e.g. ['id' => 123] for /user/123).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $routeParams = null;

    /**
     * Request path including query string (e.g. /user/123?page=2) for linking to the exact URL that was hit.
     */
    #[ORM\Column(type: Types::STRING, length: 2048, nullable: true)]
    private ?string $routePath = null;

    /**
     * Creates a new instance.
     */
    public function __construct()
    {
        $this->accessedAt = new DateTimeImmutable();
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
     * @return DateTimeImmutable The access timestamp
     */
    public function getAccessedAt(): DateTimeImmutable
    {
        return $this->accessedAt;
    }

    /**
     * Set the access timestamp.
     *
     * @param DateTimeImmutable $accessedAt The access timestamp
     */
    public function setAccessedAt(DateTimeImmutable $accessedAt): self
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

    /**
     * Get the unique request identifier.
     *
     * @return string|null The request ID (null for records created before this field or from CLI)
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Set the unique request identifier.
     *
     * @param string|null $requestId The request ID
     */
    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $requestId;

        return $this;
    }

    /**
     * Get the HTTP Referer header.
     *
     * @return string|null The referer URL (null if not sent)
     */
    public function getReferer(): ?string
    {
        return $this->referer;
    }

    /**
     * Set the HTTP Referer header.
     *
     * @param string|null $referer The referer URL
     */
    public function setReferer(?string $referer): self
    {
        $this->referer = $referer !== null && strlen($referer) > 2048 ? substr($referer, 0, 2048) : $referer;

        return $this;
    }

    /**
     * Get the logged-in user identifier (username, email, etc.).
     *
     * @return string|null The user identifier or null if not logged in
     */
    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    /**
     * Set the logged-in user identifier.
     *
     * @param string|null $userIdentifier The user identifier (max 255 chars)
     */
    public function setUserIdentifier(?string $userIdentifier): self
    {
        $this->userIdentifier = $userIdentifier !== null && strlen($userIdentifier) > 255 ? substr($userIdentifier, 0, 255) : $userIdentifier;

        return $this;
    }

    /**
     * Get the logged-in user ID (stringified).
     *
     * @return string|null The user ID or null if not available
     */
    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * Set the logged-in user ID (stringified, e.g. int or UUID).
     *
     * @param string|null $userId The user ID (max 64 chars)
     */
    public function setUserId(?string $userId): self
    {
        $this->userId = $userId !== null && strlen($userId) > 64 ? substr($userId, 0, 64) : $userId;

        return $this;
    }

    /**
     * Get the route parameters for this request.
     *
     * @return array|null Route params (e.g. ['id' => 123])
     */
    public function getRouteParams(): ?array
    {
        return $this->routeParams;
    }

    /**
     * Set the route parameters for this request.
     *
     * @param array|null $routeParams Route params
     */
    public function setRouteParams(?array $routeParams): self
    {
        $this->routeParams = $routeParams;

        return $this;
    }

    /**
     * Get the request path including query string (e.g. /user/123?page=2) for linking.
     *
     * @return string|null The path or null
     */
    public function getRoutePath(): ?string
    {
        return $this->routePath;
    }

    /**
     * Set the request path.
     *
     * @param string|null $routePath The path including query string (max 2048 chars)
     */
    public function setRoutePath(?string $routePath): self
    {
        $this->routePath = $routePath !== null && strlen($routePath) > 2048 ? substr($routePath, 0, 2048) : $routePath;

        return $this;
    }
}
