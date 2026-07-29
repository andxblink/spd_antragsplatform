<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use app\models\db\Consultation;
use app\models\layoutHooks\Hooks;
use app\plugins\ModuleBase;
use yii\base\InvalidConfigException;
use yii\web\Application;
use yii\web\Cookie;
use yii\web\Response;
use yii\web\View;

/**
 * SPD Antragsplatform layout.
 *
 * Registered as a selectable layout rather than a forced one, matching
 * plugins/green_layout. The consultation admin picks it under
 * Einstellungen > Erscheinungsbild. Forcing it would remove that choice and
 * make the instance harder to debug when the layout itself is at fault.
 *
 * The layout key must stay 'std'. Layout.php stores this as
 * "layout-plugin-spd_layout-std", so renaming the key orphans consultations
 * that already reference it.
 */
class Module extends ModuleBase
{
    public function init(): void
    {
        parent::init();

        if (!\Yii::$app instanceof Application) {
            return;
        }

        \Yii::$app->on(Application::EVENT_BEFORE_REQUEST, static function (): void {
            [$route] = \Yii::$app->getRequest()->resolve();
            if ($route !== 'user/recovery') {
                return;
            }

            $existing = \Yii::$app->controllerMap['user'] ?? null;
            if ($existing !== null && $existing !== RecoveryController::class) {
                throw new InvalidConfigException(
                    'spd_layout cannot install its recovery controller because user is already mapped'
                );
            }
            \Yii::$app->controllerMap['user'] = RecoveryController::class;
        });

        \Yii::$app->on(Application::EVENT_AFTER_REQUEST, static function (): void {
            $response = \Yii::$app->getResponse();
            if (!$response instanceof Response) {
                return;
            }
            $headers = $response->headers;
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $headers->set('X-Content-Type-Options', 'nosniff');
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
            $headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
            $headers->remove('X-Powered-By');
        });
    }

    /**
     * @return class-string<\app\models\settings\Consultation>
     */
    public static function getConsultationSettingsClass(Consultation $consultation): string
    {
        return ConsultationSettings::class;
    }

    /**
     * Production runs behind HTTPS at Caddy, while local test servers remain
     * plain HTTP. The environment-owned protocol decides whether cookies must
     * carry Secure, before Yii creates the session and identity components.
     *
     * @return array{secure: bool, httpOnly: bool, sameSite: string}|array{}
     */
    public static function getSessionCookieSettings(): array
    {
        if (strtolower(trim((string) getenv('APP_PROTOCOL'))) !== 'https') {
            return [];
        }

        return [
            'secure' => true,
            'httpOnly' => true,
            'sameSite' => Cookie::SAME_SITE_LAX,
        ];
    }

    /**
     * Register a German wording variant, selectable as the consultation's
     * wordingBase ("Sprache anpassen" > "Plugin: spd_layout").
     *
     * MessageSource merges `messages/de/<category>.php` from this plugin over
     * upstream's own, so the files here hold only the strings that named the
     * upstream product. This is the versioned route; the alternative,
     * per-consultation `consultationText` rows, lives only in the database and
     * would not survive creating a second consultation.
     *
     * @return string[]
     */
    public static function getProvidedTranslations(): array
    {
        return ['de'];
    }

    /**
     * @return array<string, array{title: string, preview: string|null, bundle: class-string, hooks?: class-string<Hooks>, odtTemplate?: string}>
     */
    public static function getProvidedLayouts(?View $view = null): array
    {
        // Without this the appearance admin renders `<img src="">`, which every
        // browser shows as a broken-image icon beside the two upstream previews.
        // The URL is only resolvable when a view is passed, because it comes
        // from the published asset directory.
        if ($view) {
            $thumbBase = ThumbnailAssets::register($view)->baseUrl;
        } else {
            $thumbBase = '';
        }

        return [
            'std' => [
                'title'   => 'SPD Antragsplatform',
                'preview' => $thumbBase . '/thumbnails/layout-preview-spd.png',
                'bundle'  => Assets::class,
                'hooks'   => LayoutHooks::class,
            ],
        ];
    }
}
