<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use app\plugins\modern_layout\LayoutHooks as ModernLayoutHooks;
use yii\helpers\Html;

/**
 * The SPD Antragsplatform variant of the Modern layout.
 *
 * Structure, behaviour and the design system all come from
 * plugins/modern_layout. This class only supplies what is SPD's: the
 * wordmark, the QR-code mark, the AGPL source offer for this deployment, the
 * suppressed upstream promo box, and its own message category (MessageSource
 * only knows categories of enabled plugins, and modern_layout need not be
 * enabled here).
 *
 * The site carries its own name, so the wordmark and the promo box change.
 * The footer keeps upstream's attribution.
 */
class LayoutHooks extends ModernLayoutHooks
{
    private const MODIFIED_SOURCE_REPOSITORY = 'https://github.com/andxblink/spd_antragsplatform';

    protected function wordmark(): string
    {
        return 'Antragsplatform';
    }

    protected function messageCategory(): string
    {
        return 'spd_layout';
    }

    /**
     * Return a source URL only for a complete, immutable commit identifier.
     *
     * A floating branch or an operator-controlled arbitrary URL would not be
     * a reliable AGPL source offer, so the deployment must provide a 40-digit
     * commit in ANTRAGSPLATFORM_PUBLIC_SOURCE_COMMIT.
     */
    private static function modifiedSourceUrl(): ?string
    {
        $commit = strtolower(trim((string)getenv('ANTRAGSPLATFORM_PUBLIC_SOURCE_COMMIT')));
        if (!preg_match('/^[0-9a-f]{40}$/', $commit)) {
            return null;
        }

        return self::MODIFIED_SOURCE_REPOSITORY . '/tree/' . $commit;
    }

    /**
     * The AGPL section 13 offer for this modified version, after upstream's
     * attribution in the footer. Absent when no exact commit is configured.
     */
    protected function sourceOfferHtml(): string
    {
        $sourceUrl = self::modifiedSourceUrl();
        if ($sourceUrl === null) {
            return '';
        }

        return Html::a(
            $this->t('modified_source_code'),
            $sourceUrl,
            [
                'class' => 'source',
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
                'aria-label' => $this->t('aria_modified_source_code'),
            ]
        );
    }

    /**
     * The mark punched into the middle of the two-factor QR code.
     *
     * `SecondFactorAuthentication::createQrCode()` asks for this and falls back
     * to Antragsgrün's own apple-touch-icon, whose green and magenta reads as
     * another party's branding on an SPD site. It is not an attribution
     * requirement. Upstream attribution and the modified-source offer are
     * rendered separately in the footer.
     *
     * The file is the SPD wordmark from Wikimedia (public domain for copyright,
     * trademarked as a mark), rasterised white on #E3000F with padding. The QR
     * builder downsizes it to 50x50 and punches out the modules behind it, so
     * it is deliberately a plain block rather than fine artwork.
     */
    public function squareLogoPath(?string $before): ?string
    {
        return __DIR__ . '/assets/qr-logo.png';
    }

    /**
     * Suppress upstream's "Dein Antragsgrün" promo box.
     *
     * StdHooks::getAntragsgruenAd() renders an advert for antragsgruen.de,
     * which the Modern rail would show. Returning an empty string removes it
     * at the source, out of the accessibility tree as well. Upstream
     * attribution and the modified-source offer stay in the footer.
     */
    public function getAntragsgruenAd(string $before): string
    {
        return '';
    }
}
