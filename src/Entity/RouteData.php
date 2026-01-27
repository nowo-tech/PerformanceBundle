<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;

/**
 * Route performance data entity.
 *
 * Stores performance metrics for routes including request time,
 * database query count, and query execution time.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
#[ORM\Entity(repositoryClass: RouteDataRepository::class)]
#[ORM\Table(name: 'routes_data')]
#[ORM\Index(columns: ['name'], name: 'idx_route_name')]
#[ORM\Index(columns: ['env'], name: 'idx_route_env')]
#[ORM\Index(columns: ['env', 'name'], name: 'idx_route_env_name')]
#[ORM\Index(columns: ['env', 'request_time'], name: 'idx_route_env_request_time')]
#[ORM\Index(columns: ['created_at'], name: 'idx_route_created_at')]
#[ORM\Index(columns: ['env', 'access_count'], name: 'idx_route_env_access_count')]
class RouteData
{
    /**
     * Primary key identifier.
     */
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Environment name (dev, test, prod).
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $env = null;

    /**
     * Route name.
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $name = null;

    /**
     * HTTP method (GET, POST, PUT, DELETE, etc.).
     */
    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $httpMethod = null;

    /**
     * Total number of database queries executed.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $totalQueries = null;

    /**
     * Route parameters as JSON array.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $params = null;

    /**
     * Request execution time in seconds.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $requestTime = null;

    /**
     * Total database query execution time in seconds.
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $queryTime = null;

    /**
     * Peak memory usage in bytes.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $memoryUsage = null;

    /**
     * Number of times this route has been accessed.
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $accessCount = 1;

    /**
     * Creation timestamp.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Last update timestamp.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Last access timestamp.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastAccessedAt = null;

    /**
     * Whether this route has been reviewed.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $reviewed = false;

    /**
     * Review timestamp.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    /**
     * Whether queries improved after review.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $queriesImproved = null;

    /**
     * Whether request time improved after review.
     */
    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $timeImproved = null;

    /**
     * User who reviewed this route (optional).
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $reviewedBy = null;

    /**
     * HTTP status codes counts (e.g., ['200' => 100, '404' => 5, '500' => 2]).
     *
     * @var array<int, int>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $statusCodes = null;

    /**
     * Constructor.
     *
     * Initializes creation and update timestamps.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastAccessedAt = new \DateTimeImmutable();
        $this->statusCodes = [];
    }

    /**
     * Get the primary key identifier.
     *
     * @return int|null The entity ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Get the environment name.
     *
     * @return string|null The environment (dev, test, prod)
     */
    public function getEnv(): ?string
    {
        return $this->env;
    }

    /**
     * Set the environment name.
     *
     * @param string|null $env The environment name
     */
    public function setEnv(?string $env): self
    {
        $this->env = $env;

        return $this;
    }

    /**
     * Get the route name.
     *
     * @return string|null The route name
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the route name.
     *
     * @param string|null $name The route name
     */
    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the HTTP method.
     *
     * @return string|null The HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    /**
     * Set the HTTP method.
     *
     * @param string|null $httpMethod The HTTP method
     */
    public function setHttpMethod(?string $httpMethod): self
    {
        $this->httpMethod = $httpMethod;

        return $this;
    }

    /**
     * Get the total number of database queries.
     *
     * @return int|null The query count
     */
    public function getTotalQueries(): ?int
    {
        return $this->totalQueries;
    }

    /**
     * Set the total number of database queries.
     *
     * @param int|null $totalQueries The query count
     */
    public function setTotalQueries(?int $totalQueries): self
    {
        $this->totalQueries = $totalQueries;

        return $this;
    }

    /**
     * Get the route parameters.
     *
     * @return array|null The route parameters as array
     */
    public function getParams(): ?array
    {
        return $this->params;
    }

    /**
     * Set the route parameters.
     *
     * @param array|null $params The route parameters
     */
    public function setParams(?array $params): self
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Get the request execution time in seconds.
     *
     * @return float|null The request time in seconds
     */
    public function getRequestTime(): ?float
    {
        return $this->requestTime;
    }

    /**
     * Set the request execution time in seconds.
     *
     * @param float|null $requestTime The request time in seconds
     */
    public function setRequestTime(?float $requestTime): self
    {
        $this->requestTime = $requestTime;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Get the total query execution time in seconds.
     *
     * @return float|null The query time in seconds
     */
    public function getQueryTime(): ?float
    {
        return $this->queryTime;
    }

    /**
     * Set the total query execution time in seconds.
     *
     * @param float|null $queryTime The query time in seconds
     */
    public function setQueryTime(?float $queryTime): self
    {
        $this->queryTime = $queryTime;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Get the creation timestamp.
     *
     * @return \DateTimeImmutable|null The creation date
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Set the creation timestamp.
     *
     * @param \DateTimeImmutable|null $createdAt The creation date
     */
    public function setCreatedAt(?\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get the last update timestamp.
     *
     * @return \DateTimeImmutable|null The update date
     */
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Set the last update timestamp.
     *
     * @param \DateTimeImmutable|null $updatedAt The update date
     */
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get the peak memory usage in bytes.
     *
     * @return int|null The memory usage in bytes
     */
    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    /**
     * Set the peak memory usage in bytes.
     *
     * @param int|null $memoryUsage The memory usage in bytes
     */
    public function setMemoryUsage(?int $memoryUsage): self
    {
        $this->memoryUsage = $memoryUsage;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Get the number of times this route has been accessed.
     *
     * @return int The access count
     */
    public function getAccessCount(): int
    {
        return $this->accessCount;
    }

    /**
     * Set the number of times this route has been accessed.
     *
     * @param int $accessCount The access count
     */
    public function setAccessCount(int $accessCount): self
    {
        $this->accessCount = $accessCount;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Increment the access count by one.
     */
    public function incrementAccessCount(): self
    {
        ++$this->accessCount;
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastAccessedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Get the last access timestamp.
     *
     * @return \DateTimeImmutable|null The last access date or null if not available
     */
    public function getLastAccessedAt(): ?\DateTimeImmutable
    {
        return $this->lastAccessedAt;
    }

    /**
     * Set the last access timestamp.
     *
     * @param \DateTimeImmutable|null $lastAccessedAt The last access date
     */
    public function setLastAccessedAt(?\DateTimeImmutable $lastAccessedAt): self
    {
        $this->lastAccessedAt = $lastAccessedAt;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Check if this record should be updated based on worse performance metrics.
     *
     * Returns true if the new metrics indicate worse performance (higher request time
     * or more queries) than the current stored values.
     *
     * @param float|null $newRequestTime  The new request time to compare
     * @param int|null   $newTotalQueries The new query count to compare
     *
     * @return bool True if the record should be updated, false otherwise
     */
    public function shouldUpdate(?float $newRequestTime, ?int $newTotalQueries): bool
    {
        // Update if request time is worse (higher)
        if (null !== $newRequestTime && null !== $this->requestTime && $newRequestTime > $this->requestTime) {
            return true;
        }

        // Update if query count is worse (higher)
        if (null !== $newTotalQueries && null !== $this->totalQueries && $newTotalQueries > $this->totalQueries) {
            return true;
        }

        // Update if we have new data but no existing data
        return (null === $this->requestTime && null !== $newRequestTime)
            || (null === $this->totalQueries && null !== $newTotalQueries);
    }

    /**
     * Check if this route has been reviewed.
     *
     * @return bool True if reviewed, false otherwise
     */
    public function isReviewed(): bool
    {
        return $this->reviewed;
    }

    /**
     * Set whether this route has been reviewed.
     *
     * @param bool $reviewed Whether the route is reviewed
     */
    public function setReviewed(bool $reviewed): self
    {
        $this->reviewed = $reviewed;

        return $this;
    }

    /**
     * Get the review timestamp.
     *
     * @return \DateTimeImmutable|null The review date
     */
    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    /**
     * Set the review timestamp.
     *
     * @param \DateTimeImmutable|null $reviewedAt The review date
     */
    public function setReviewedAt(?\DateTimeImmutable $reviewedAt): self
    {
        $this->reviewedAt = $reviewedAt;

        return $this;
    }

    /**
     * Get whether queries improved after review.
     *
     * @return bool|null True if improved, false if not, null if not specified
     */
    public function getQueriesImproved(): ?bool
    {
        return $this->queriesImproved;
    }

    /**
     * Set whether queries improved after review.
     *
     * @param bool|null $queriesImproved True if improved, false if not, null if not specified
     */
    public function setQueriesImproved(?bool $queriesImproved): self
    {
        $this->queriesImproved = $queriesImproved;

        return $this;
    }

    /**
     * Get whether request time improved after review.
     *
     * @return bool|null True if improved, false if not, null if not specified
     */
    public function getTimeImproved(): ?bool
    {
        return $this->timeImproved;
    }

    /**
     * Set whether request time improved after review.
     *
     * @param bool|null $timeImproved True if improved, false if not, null if not specified
     */
    public function setTimeImproved(?bool $timeImproved): self
    {
        $this->timeImproved = $timeImproved;

        return $this;
    }

    /**
     * Mark this route as reviewed with optional improvement information.
     *
     * @param bool|null $queriesImproved Whether queries improved
     * @param bool|null $timeImproved Whether time improved
     * @param string|null $reviewedBy The reviewer username
     * @return self
     */
    public function markAsReviewed(?bool $queriesImproved = null, ?bool $timeImproved = null, ?string $reviewedBy = null): self
    {
        $this->reviewed = true;
        $this->reviewedAt = new \DateTimeImmutable();
        
        if ($queriesImproved !== null) {
            $this->queriesImproved = $queriesImproved;
        }
        
        if ($timeImproved !== null) {
            $this->timeImproved = $timeImproved;
        }
        
        if ($reviewedBy !== null) {
            $this->reviewedBy = $reviewedBy;
        }
        
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Get the user who reviewed this route.
     *
     * @return string|null The reviewer identifier
     */
    public function getReviewedBy(): ?string
    {
        return $this->reviewedBy;
    }

    /**
     * Set the user who reviewed this route.
     *
     * @param string|null $reviewedBy The reviewer identifier
     */
    public function setReviewedBy(?string $reviewedBy): self
    {
        $this->reviewedBy = $reviewedBy;

        return $this;
    }

    /**
     * String representation of the entity.
     *
     * Returns a human-readable string representation of the route data.
     *
     * @return string String representation
     */
    public function __toString(): string
    {
        $parts = [];

        if (null !== $this->httpMethod) {
            $parts[] = $this->httpMethod;
        }

        if (null !== $this->name) {
            $parts[] = $this->name;
        }

        if (null !== $this->env) {
            $parts[] = \sprintf('(%s)', $this->env);
        }

        if (null !== $this->requestTime) {
            $parts[] = \sprintf('%.2fms', $this->requestTime * 1000);
        }

        if (null !== $this->totalQueries) {
            $parts[] = \sprintf('%dq', $this->totalQueries);
        }

        if (empty($parts)) {
            return \sprintf('RouteData#%s', $this->id ?? 'new');
        }

        return implode(' ', $parts);
    }

    /**
     * Get HTTP status codes counts.
     *
     * @return array<int, int>|null Status codes counts (e.g., [200 => 100, 404 => 5])
     */
    public function getStatusCodes(): ?array
    {
        return $this->statusCodes;
    }

    /**
     * Set HTTP status codes counts.
     *
     * @param array<int, int>|null $statusCodes Status codes counts
     * @return self
     */
    public function setStatusCodes(?array $statusCodes): self
    {
        $this->statusCodes = $statusCodes;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Increment count for a specific HTTP status code.
     *
     * @param int $statusCode The HTTP status code
     * @return self
     */
    public function incrementStatusCode(int $statusCode): self
    {
        if ($this->statusCodes === null) {
            $this->statusCodes = [];
        }

        if (!isset($this->statusCodes[$statusCode])) {
            $this->statusCodes[$statusCode] = 0;
        }

        $this->statusCodes[$statusCode]++;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Get the count for a specific HTTP status code.
     *
     * @param int $statusCode The HTTP status code
     * @return int The count for the status code (0 if not found)
     */
    public function getStatusCodeCount(int $statusCode): int
    {
        return $this->statusCodes[$statusCode] ?? 0;
    }

    /**
     * Get the ratio (percentage) for a specific HTTP status code.
     *
     * @param int $statusCode The HTTP status code
     * @return float The ratio as a percentage (0.0 to 100.0)
     */
    public function getStatusCodeRatio(int $statusCode): float
    {
        if ($this->statusCodes === null || empty($this->statusCodes)) {
            return 0.0;
        }

        $total = array_sum($this->statusCodes);
        if ($total === 0) {
            return 0.0;
        }

        $count = $this->getStatusCodeCount($statusCode);
        return ($count / $total) * 100.0;
    }

    /**
     * Get total responses tracked (sum of all status code counts).
     *
     * @return int Total number of responses
     */
    public function getTotalResponses(): int
    {
        if ($this->statusCodes === null || empty($this->statusCodes)) {
            return 0;
        }

        return array_sum($this->statusCodes);
    }

    /**
     * Access records for temporal analysis.
     *
     * @var \Doctrine\Common\Collections\Collection<int, RouteDataRecord>
     */
    #[ORM\OneToMany(targetEntity: RouteDataRecord::class, mappedBy: 'routeData', cascade: ['remove'])]
    private \Doctrine\Common\Collections\Collection $accessRecords;

    /**
     * Get access records.
     *
     * @return \Doctrine\Common\Collections\Collection<int, RouteDataRecord> Access records
     */
    public function getAccessRecords(): \Doctrine\Common\Collections\Collection
    {
        return $this->accessRecords;
    }

    /**
     * Add an access record.
     *
     * @param RouteDataRecord $accessRecord The access record
     * @return self
     */
    public function addAccessRecord(RouteDataRecord $accessRecord): self
    {
        if (!$this->accessRecords->contains($accessRecord)) {
            $this->accessRecords->add($accessRecord);
            $accessRecord->setRouteData($this);
        }

        return $this;
    }

    /**
     * Remove an access record.
     *
     * @param RouteDataRecord $accessRecord The access record
     * @return self
     */
    public function removeAccessRecord(RouteDataRecord $accessRecord): self
    {
        if ($this->accessRecords->removeElement($accessRecord)) {
            if ($accessRecord->getRouteData() === $this) {
                $accessRecord->setRouteData(null);
            }
        }

        return $this;
    }
}
