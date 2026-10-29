<?php

declare(strict_types=1);

namespace Nowo\PerformanceBundle\Service;

use function http_build_query;
use function is_array;
use function is_string;
use function parse_str;
use function preg_match;
use function strpos;
use function strtolower;
use function substr;

/**
 * Redacts secrets from what the bundle stores about a request (route path query string, referer, route parameters):
 * reset / OAuth / signed-URL tokens and similar values must not end up in the access records, the dashboard or the
 * CSV / JSON exports.
 */
final class SensitiveDataRedactor
{
    public const REDACTED = '[redacted]';

    /** Key names (or `_` / `-` separated parts of them) whose values are never stored. */
    private const SENSITIVE = '/(^|[_\-.])(token|access_token|refresh_token|id_token|code|state|nonce|signature|sig|hash|_hash|expires|password|passwd|pwd|secret|key|apikey|api_key|auth|authorization|jwt|otp|session|sid|ticket)($|[_\-.])/';

    public static function isSensitiveKey(string $key): bool
    {
        return preg_match(self::SENSITIVE, strtolower($key)) === 1;
    }

    /**
     * Redacts the values of sensitive query parameters of a URL or `path?query`, keeping everything else.
     */
    public static function redactUrl(string $url): string
    {
        $pos = strpos($url, '?');
        if ($pos === false) {
            return $url;
        }

        // Referers / request paths never need the fragment; drop it with the query string handling.
        $query = substr($url, $pos + 1);
        $hash  = strpos($query, '#');
        if ($hash !== false) {
            $query = substr($query, 0, $hash);
        }

        parse_str($query, $params);
        /** @var array<array-key, mixed> $params */
        $redacted = self::redactParams($params);
        $rebuilt  = http_build_query($redacted);

        return substr($url, 0, $pos) . ($rebuilt !== '' ? '?' . $rebuilt : '');
    }

    /**
     * @param array<array-key, mixed> $params
     *
     * @return array<array-key, mixed>
     */
    public static function redactParams(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $params[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $params[$key] = self::redactParams($value);
            }
        }

        return $params;
    }
}
