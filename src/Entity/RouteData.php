<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\PerformanceBundle\Repository\RouteDataRepository;

/**
 * Route identity and metadata entity (normalized).
 *
 * Stores only route identity (env, name, httpMethod, params) and usage/review metadata.
 * All performance metrics (request time, query count, status codes, etc.) are derived
 * from RouteDataRecord; use aggregates from records for listings and rankings.
 *
 * @see RouteDataRecord
 * @see docs/ENTITY_NORMALIZATION_PLAN.md
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[ORM\Entity(repositoryClass: RouteDataRepository::class)]
#[ORM\Table(name: 'routes_data')]
#[ORM\Index(columns: ['name'], name: 'idx_route_name')]
#[ORM\Index(columns: ['env'], name: 'idx_route_env')]
#[ORM\Index(columns: ['env', 'name'], name: 'idx_route_env_name')]
#[ORM\Index(columns: ['created_at'], name: 'idx_route_created_at')]
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
     * Route parameters as JSON array.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $params = null;

    /**
     * Creation timestamp.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Last access timestamp (updated when a RouteDataRecord is inserted or via command).
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
     * Whether to save access records (RouteDataRecord) for this route.
     * When false, metrics are still aggregated in RouteData but individual access records are not stored.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $saveAccessRecords = true;

    /**
     * Creates a new instance.
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastAccessedAt = new \DateTimeImmutable();
        $this->accessRecords = new \Doctrine\Common\Collections\ArrayCollection();
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

        return $this;
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
     * @param bool|null   $queriesImproved Whether queries improved
     * @param bool|null   $timeImproved    Whether time improved
     * @param string|null $reviewedBy      The reviewer username
     */
    public function markAsReviewed(?bool $queriesImproved = null, ?bool $timeImproved = null, ?string $reviewedBy = null): self
    {
        $this->reviewed = true;
        $this->reviewedAt = new \DateTimeImmutable();

        if (null !== $queriesImproved) {
            $this->queriesImproved = $queriesImproved;
        }

        if (null !== $timeImproved) {
            $this->timeImproved = $timeImproved;
        }

        if (null !== $reviewedBy) {
            $this->reviewedBy = $reviewedBy;
        }

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

        if (empty($parts)) {
            return \sprintf('RouteData#%s', $this->id ?? 'new');
        }

        return implode(' ', $parts);
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

    /**
     * Whether to save access records for this route.
     *
     * @return bool True to save access records (default), false to skip
     */
    public function getSaveAccessRecords(): bool
    {
        return $this->saveAccessRecords;
    }

    /**
     * Set whether to save access records for this route.
     *
     * @param bool $saveAccessRecords True to save access records, false to skip
     */
    public function setSaveAccessRecords(bool $saveAccessRecords): self
    {
        $this->saveAccessRecords = $saveAccessRecords;

        return $this;
    }
}
