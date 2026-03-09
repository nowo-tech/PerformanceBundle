<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Model;

/**
 * DTO for purge access records form (POST).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @property string $env Environment (e.g. dev, prod) or empty for all
 * @property string $purgeType 'all' or 'older_than'
 * @property int|null $days Days for older_than (records with accessed_at older than this many days)
 */
final class PurgeAccessRecordsRequest
{
    public const PURGE_ALL        = 'all';
    public const PURGE_OLDER_THAN = 'older_than';

    public function __construct(
        public string $env = '',
        public string $purgeType = self::PURGE_OLDER_THAN,
        public ?int $days = 30,
    ) {
    }
}
