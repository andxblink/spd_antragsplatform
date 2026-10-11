<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

/**
 * Compatibility seam for the upstream recovery controller's transport result.
 *
 * Antragsgrün v4.17.1 reports delivery failures through a translated session
 * flash rather than a typed result. Keep that boundary small and explicit so
 * the transaction adapter can be regression-tested without booting Yii. If a
 * future upstream release exposes a structured delivery result, replace this
 * parser at the seam instead of changing the legal-record or recovery flow.
 */
final class RecoveryDeliveryResult
{
    /**
     * Return whether a flash payload contains the upstream transport marker.
     *
     * @param string|array<int, mixed>|null $flash
     */
    public static function reportsFailure(string|array|null $flash, string $failureMarker): bool
    {
        if (is_array($flash)) {
            $flash = implode("\n", array_map(
                static fn(mixed $value): string => (string)$value,
                $flash
            ));
        }

        if (!is_string($flash) || $failureMarker === '') {
            return false;
        }

        return str_contains($flash, $failureMarker);
    }
}
