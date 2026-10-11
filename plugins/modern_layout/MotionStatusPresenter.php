<?php

declare(strict_types=1);

namespace app\plugins\modern_layout;

use app\components\UrlHelper;
use app\models\db\{Amendment, IMotion, Motion};
use yii\helpers\Html;

/**
 * Adds a readable presentation around Antragsgrün's authoritative status.
 *
 * The model remains the source of truth. This class never replaces the
 * configured status label, status link, or statusString with a guessed
 * translation. That boundary is important for legal records and custom
 * workflows.
 */
final class MotionStatusPresenter
{
    public function __construct(private readonly string $messageCategory)
    {
    }

    private function kind(IMotion $item): string
    {
        return StatusPresentation::kind((int)$item->status);
    }

    private function dateLine(IMotion $item): ?string
    {
        $line = StatusPresentation::dateLine(
            (int)$item->status,
            $item->dateResolution,
            $item->dateSubmission,
            $item->dateCreation
        );

        return $line === null ? null : str_replace('%DATE%', $line['date'], $this->t($line['key']));
    }

    private function t(string $key): string
    {
        return \Yii::t($this->messageCategory, $key);
    }

    private function itemId(IMotion $item): int
    {
        return (int)$item->id;
    }

    /**
     * Keep Antragsgrün's status text and links, correcting only its legacy
     * closing-tag typo so configured custom status context remains valid HTML.
     */
    private function authoritativeStatus(Motion|Amendment $item): string
    {
        return str_replace('</string>', '</small>', $item->getFormattedStatus());
    }

    /**
     * Render a detail summary without changing model state or permissions.
     */
    public function renderDetail(Motion|Amendment $item): string
    {
        $titleId = 'modernMotionStatusTitle' . $this->itemId($item);
        $statusHtml = $this->authoritativeStatus($item);

        $out = '<section class="modernMotionStatusSummary modernStatus-' .
            Html::encode($this->kind($item)) . '" data-status-code="' .
            Html::encode((string)(int)$item->status) . '" aria-labelledby="' .
            Html::encode($titleId) . '">';
        $heading = Html::encode($this->t('status_heading'));
        $out .= '<div class="modernStatusEyebrow">' . $heading . '</div>';
        $out .= '<h2 id="' . Html::encode($titleId) . '">' . $heading . '</h2>';
        $out .= '<p class="modernStatusSource"><strong>' . Html::encode($this->t('status_label')) . '</strong> ' .
            $statusHtml . '</p>';
        if ($date = $this->dateLine($item)) {
            $out .= '<p class="modernStatusDate">' . Html::encode($date) . '</p>';
        }
        if ($item instanceof Motion && $item->status === IMotion::STATUS_DRAFT && $item->canEditText()) {
            $url = UrlHelper::createMotionUrl($item, 'createconfirm', ['fromMode' => 'create']);
            $out .= Html::a(Html::encode($this->t('status_draft_submit')), $url, [
                'class' => 'btn btn-primary modernStatusAction',
            ]);
        }
        return $out . '</section>';
    }

    public function appendListStatus(string $line, Motion $motion): string
    {
        return $this->appendItemListStatus($line, $motion, 'motion');
    }

    public function appendAmendmentListStatus(string $line, Amendment $amendment): string
    {
        return $this->appendItemListStatus($line, $amendment, 'amendment');
    }

    private function appendItemListStatus(string $line, Motion|Amendment $item, string $type): string
    {
        $statusHtml = $this->authoritativeStatus($item);
        $out = '<p class="modernListStatus modernStatus-' . Html::encode($this->kind($item)) .
            '" data-status-type="' . Html::encode($type) . '" data-status-code="' .
            Html::encode((string)(int)$item->status) . '">';
        $out .= '<span class="modernListStatusLabel"><strong>' . Html::encode($this->t('status_label')) .
            '</strong> ' . $statusHtml . '</span>';
        return $line . $out . '</p>';
    }
}
