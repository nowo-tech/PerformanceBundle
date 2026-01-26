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
#[ORM\Index(columns: ['name'], name: 'idx_route_name')]
#[ORM\Index(columns: ['env'], name: 'idx_route_env')]
class RouteData
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
     * Environment name (dev, test, prod).
     *
     * @var string|null
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $env = null;

    /**
     * Route name.
     *
     * @var string|null
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $name = null;

    /**
     * Total number of database queries executed.
     *
     * @var int|null
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $totalQueries = null;

    /**
     * Route parameters as JSON array.
     *
     * @var array|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $params = null;

    /**
     * Request execution time in seconds.
     *
     * @var float|null
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $requestTime = null;

    /**
     * Total database query execution time in seconds.
     *
     * @var float|null
     */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $queryTime = null;

    /**
     * Creation timestamp.
     *
     * @var \DateTimeImmutable|null
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Last update timestamp.
     *
     * @var \DateTimeImmutable|null
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Constructor.
     *
     * Initializes creation and update timestamps.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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
     * @return self
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
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name;

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
     * @return self
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
     * @return self
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
     * @return self
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
     * @return self
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
     * @return self
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
     * @return self
     */
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Check if this record should be updated based on worse performance metrics.
     *
     * Returns true if the new metrics indicate worse performance (higher request time
     * or more queries) than the current stored values.
     *
     * @param float|null $newRequestTime The new request time to compare
     * @param int|null $newTotalQueries The new query count to compare
     * @return bool True if the record should be updated, false otherwise
     */
    public function shouldUpdate(?float $newRequestTime, ?int $newTotalQueries): bool
    {
        // Update if request time is worse (higher)
        if ($newRequestTime !== null && $this->requestTime !== null && $newRequestTime > $this->requestTime) {
            return true;
        }

        // Update if query count is worse (higher)
        if ($newTotalQueries !== null && $this->totalQueries !== null && $newTotalQueries > $this->totalQueries) {
            return true;
        }

        // Update if we have new data but no existing data
        return ($this->requestTime === null && $newRequestTime !== null)
            || ($this->totalQueries === null && $newTotalQueries !== null);
    }
}
