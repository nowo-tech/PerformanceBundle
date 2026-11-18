<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Concurrency;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * A dashboard write met a database lock conflict (deadlock, lock wait timeout, serialization failure): the HTTP answer
 * of REQ-RUNTIME-003 rule 6, rendered by the host's error page.
 *
 * - {@see self::CODE_RETRY_SAFE} (CONFLICT_001): nothing was committed; the answer carries `Retry-After` and the
 *   client may simply send the request again.
 * - {@see self::CODE_PARTIALLY_SAVED} (CONFLICT_002): part of the work was already committed (a chunked purge); no
 *   `Retry-After`: the client reads the data again before deciding to repeat.
 *
 * The message is constant and client-safe: the storage error (which can hold SQL and parameters) stays in the previous
 * exception, for the logs only.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class WriteConflictException extends HttpException
{
    public const CODE_RETRY_SAFE      = 'CONFLICT_001';
    public const CODE_PARTIALLY_SAVED = 'CONFLICT_002';

    public const MESSAGE_RETRY_SAFE      = 'CONFLICT_001: the same data was being changed at the same time. Nothing was saved: try again.';
    public const MESSAGE_PARTIALLY_SAVED = 'CONFLICT_002: the same data was being changed at the same time and part of the change was saved. Reload the page before trying again.';

    /**
     * @param string $errorCode {@see self::CODE_RETRY_SAFE} or {@see self::CODE_PARTIALLY_SAVED}
     * @param int $statusCode 409 by default (503 when the host configured it)
     * @param array<string, string> $headers Response headers (Retry-After, Cache-Control)
     * @param Throwable|null $previous The storage failure
     */
    public function __construct(
        public readonly string $errorCode,
        int $statusCode,
        array $headers,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $statusCode,
            $errorCode === self::CODE_PARTIALLY_SAVED ? self::MESSAGE_PARTIALLY_SAVED : self::MESSAGE_RETRY_SAFE,
            $previous,
            $headers,
        );
    }

    /**
     * Whether part of the work was already committed (CONFLICT_002).
     */
    public function isPartiallySaved(): bool
    {
        return $this->errorCode === self::CODE_PARTIALLY_SAVED;
    }
}
