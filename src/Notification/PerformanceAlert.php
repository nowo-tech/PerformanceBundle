<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Notification;

/**
 * Represents a performance alert.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2025 Nowo.tech
 */
final class PerformanceAlert
{
    /**
     * Alert severity levels.
     */
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    /**
     * Alert types.
     */
    public const TYPE_REQUEST_TIME = 'request_time';
    public const TYPE_QUERY_COUNT = 'query_count';
    public const TYPE_QUERY_TIME = 'query_time';
    public const TYPE_MEMORY_USAGE = 'memory_usage';
    public const TYPE_OUTLIER = 'outlier';

    /**
     * Constructor.
     *
     * @param string $type Alert type (request_time, query_count, etc.)
     * @param string $severity Alert severity (warning, critical)
     * @param string $message Alert message
     * @param array<string, mixed> $context Additional context data
     */
    public function __construct(
        private readonly string $type,
        private readonly string $severity,
        private readonly string $message,
        private readonly array $context = []
    ) {
    }

    /**
     * Get the alert type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the alert severity.
     *
     * @return string
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Get the alert message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get additional context data.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get a context value by key.
     *
     * @param string $key The context key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Check if this is a critical alert.
     *
     * @return bool
     */
    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * Check if this is a warning alert.
     *
     * @return bool
     */
    public function isWarning(): bool
    {
        return $this->severity === self::SEVERITY_WARNING;
    }
}
