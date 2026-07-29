<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use app\components\{Tools, UrlHelper};
use app\models\db\{Amendment, IMotion, Motion};
use yii\helpers\Html;

/**
 * Adds a readable presentation around Antragsgrün's authoritative status.
 *
 * The model remains the source of truth. In particular, this class never
 * replaces the configured status label, status link, or statusString with a
 * guessed translation. That matters for legal records and custom workflows.
 */
final class MotionStatusPresenter
{
    /**
     * Return presentation metadata without translating the configured status.
     *
     * The status engine owns the label and any workflow-specific meaning. A
     * generic sentence derived from that label is safe to repeat, while a
     * guessed next step is not. Custom and unknown states intentionally have
     * no additive prose.
     *
     * @return array{kind: string, summary: string|null}
     */
    private static function describe(IMotion $item): array
    {
        $status = (int)$item->status;
        $kind = match ($status) {
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
            default => 'other',
        };

        $summary = null;
        if ($status !== IMotion::STATUS_CUSTOM_STRING) {
            $statusName = trim($item->getMyConsultation()->getStatuses()->getStatusName($status));
            if ($statusName !== '' && $statusName !== '???') {
                $summary = 'Status: ' . $statusName;
            }
        }

        return [
            'kind' => $kind,
            'summary' => $summary,
        ];
    }

    private static function dateLine(IMotion $item): ?string
    {
        if ($item->dateResolution) {
            return 'Entschieden am ' . Tools::formatMysqlDate($item->dateResolution, false);
        }
        if ($item->dateSubmission) {
            return 'Eingereicht am ' . Tools::formatMysqlDateTime($item->dateSubmission, false);
        }
        if ($item->status === IMotion::STATUS_DRAFT && $item->dateCreation) {
            return 'Gespeichert am ' . Tools::formatMysqlDateTime($item->dateCreation, false);
        }

        return null;
    }

    private static function itemId(IMotion $item): int
    {
        return (int)$item->id;
    }

    /**
     * Keep Antragsgrün's status text and links, but repair its legacy typo in
     * the custom-status suffix. Motion::getFormattedStatus() closes its
     * <small> element as </string>, which leaves the list DOM invalid whenever
     * a configured statusString is present. This normalization changes no
     * visible or legal content and applies equally to detail and list views.
     */
    private static function authoritativeStatus(Motion|Amendment $item): string
    {
        return str_replace('</string>', '</small>', $item->getFormattedStatus());
    }

    /**
     * Render the authoritative status and an optional plain-language aid.
     *
     * @param Motion|Amendment $item
     */
    public static function renderDetail(Motion|Amendment $item): string
    {
        $description = self::describe($item);
        $titleId = 'spdMotionStatusTitle' . self::itemId($item);
        $statusHtml = self::authoritativeStatus($item);

        $out = '<section class="spdMotionStatusSummary spdStatus-' .
            Html::encode($description['kind']) . '" data-status-code="' .
            Html::encode((string)(int)$item->status) . '" aria-labelledby="' .
            Html::encode($titleId) . '">';
        $out .= '<div class="spdStatusEyebrow">Aktueller Stand</div>';
        $out .= '<h2 id="' . Html::encode($titleId) . '">Aktueller Stand</h2>';
        $out .= '<p class="spdStatusSource"><strong>Status:</strong> ' . $statusHtml . '</p>';
        if ($description['summary'] !== null) {
            $out .= '<p class="spdStatusSummaryText">' . Html::encode($description['summary']) . '</p>';
        }
        if ($date = self::dateLine($item)) {
            $out .= '<p class="spdStatusDate">' . Html::encode($date) . '</p>';
        }
        if ($item instanceof Motion && $item->status === IMotion::STATUS_DRAFT && $item->canEditText()) {
            $url = UrlHelper::createMotionUrl($item, 'createconfirm', ['fromMode' => 'create']);
            $out .= Html::a('Entwurf prüfen und einreichen', $url, [
                'class' => 'btn btn-primary spdStatusAction',
            ]);
        }
        $out .= '</section>';

        return $out;
    }

    /**
     * Append the upstream status to a consultation motion row.
     */
    public static function appendListStatus(string $line, Motion $motion): string
    {
        return self::appendItemListStatus($line, $motion, 'motion');
    }

    /**
     * Append the upstream status to a consultation amendment row.
     */
    public static function appendAmendmentListStatus(string $line, Amendment $amendment): string
    {
        return self::appendItemListStatus($line, $amendment, 'amendment');
    }

    private static function appendItemListStatus(string $line, Motion|Amendment $item, string $type): string
    {
        $description = self::describe($item);
        $statusHtml = self::authoritativeStatus($item);

        $out = '<p class="spdListStatus spdStatus-' . Html::encode($description['kind']) .
            '" data-status-type="' . Html::encode($type) . '" data-status-code="' .
            Html::encode((string)(int)$item->status) . '">';
        $out .= '<span class="spdListStatusLabel"><strong>Status:</strong> ' . $statusHtml . '</span>';
        if ($description['summary'] !== null) {
            $out .= '<span class="spdListStatusSummary">' .
                Html::encode($description['summary']) . '</span>';
        }
        $out .= '</p>';

        return $line . $out;
    }
}
