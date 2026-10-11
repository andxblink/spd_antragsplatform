<?php

declare(strict_types=1);

namespace app\plugins\modern_layout;

use app\components\Tools;
use app\models\db\IMotion;

/**
 * Pure presentation metadata for an upstream motion or amendment record.
 *
 * The record itself remains authoritative. This value-only seam makes the
 * user-visible additions testable without constructing an ActiveRecord or
 * touching status, text, vote, authorship, or publication fields.
 */
final class StatusPresentation
{
    /**
     * Classify a status for styling hooks (`modernStatus-<kind>`).
     *
     * Deliberately no wording: the visible status is always upstream's own
     * formatted status, which knows special cases this cannot (a screened
     * amendment reads "Geprüft", not the generic status name). Unknown and
     * custom states are `other`.
     */
    public static function kind(int $status): string
    {
        return match ($status) {
            IMotion::STATUS_DRAFT,
            IMotion::STATUS_DRAFT_ADMIN,
            IMotion::STATUS_MERGING_DRAFT_PUBLIC,
            IMotion::STATUS_MERGING_DRAFT_PRIVATE => 'draft',
            IMotion::STATUS_COLLECTING_SUPPORTERS => 'collecting',
            IMotion::STATUS_SUBMITTED_UNSCREENED,
            IMotion::STATUS_SUBMITTED_UNSCREENED_CHECKED => 'submitted',
            IMotion::STATUS_SUBMITTED_SCREENED => 'published',
            IMotion::STATUS_ACCEPTED,
            IMotion::STATUS_ADOPTED,
            IMotion::STATUS_MODIFIED_ACCEPTED => 'accepted',
            IMotion::STATUS_REJECTED => 'rejected',
            IMotion::STATUS_REFERRED => 'referred',
            IMotion::STATUS_PROCESSED,
            IMotion::STATUS_COMPLETED => 'completed',
            IMotion::STATUS_WITHDRAWN,
            IMotion::STATUS_WITHDRAWN_INVISIBLE => 'withdrawn',
            // IMotion::STATUS_ADJOURNED ("Vertagt"), new in 4.18. A literal,
            // because the constant does not exist before 4.18 and an
            // undefined constant in a match arm throws when it is reached.
            33 => 'adjourned',
            default => 'other',
        };
    }

    /**
     * Pick the one date worth showing, as a message key and a formatted date.
     *
     * @return array{key: string, date: string}|null
     */
    public static function dateLine(
        int $status,
        ?string $dateResolution,
        ?string $dateSubmission,
        ?string $dateCreation
    ): ?array {
        if ($dateResolution) {
            return [
                'key' => 'status_date_decided',
                'date' => Tools::formatMysqlDate($dateResolution, allowRelativeDates: false),
            ];
        }
        if ($dateSubmission) {
            return [
                'key' => 'status_date_submitted',
                'date' => Tools::formatMysqlDateTime($dateSubmission, allowRelativeDates: false),
            ];
        }
        if ($status === IMotion::STATUS_DRAFT && $dateCreation) {
            return [
                'key' => 'status_date_saved',
                'date' => Tools::formatMysqlDateTime($dateCreation, allowRelativeDates: false),
            ];
        }

        return null;
    }
}
