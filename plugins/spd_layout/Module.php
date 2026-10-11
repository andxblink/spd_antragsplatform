<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use app\models\db\Consultation;
use app\models\layoutHooks\Hooks;
use app\plugins\ModuleBase;
use yii\base\InvalidConfigException;
use yii\web\Application;
use yii\web\Cookie;
use yii\web\View;

/**
 * SPD Antragsplatform layout.
 *
 * Registered as a selectable layout rather than a forced one, matching
 * plugins/green_layout. The consultation admin picks it under
 * Einstellungen > Erscheinungsbild. Forcing it would remove that choice and
 * make the instance harder to debug when the layout itself is at fault.
 *
 * The layout key must stay 'std'. Layout.php resolves a stored layout to
 * "plugin:<pluginName>:<key>", so renaming the key orphans consultations that
 * already reference it.
 */
class Module extends ModuleBase
{
    /**
     * Native recovery persists its token before attempting delivery. Keep the
     * adapter registered until upstream provides an equivalent rollback seam.
     */
    public function init(): void
    {
        parent::init();

        if (!\Yii::$app instanceof Application) {
            return;
        }

        \Yii::$app->on(Application::EVENT_BEFORE_REQUEST, static function (): void {
            $app = \Yii::$app;
            if (!$app instanceof Application) {
                return;
            }
            [$route] = $app->getRequest()->resolve();
            if ($route !== 'user/recovery') {
                return;
            }

            $existing = $app->controllerMap['user'] ?? null;
            if ($existing !== null && $existing !== RecoveryController::class) {
                throw new InvalidConfigException(
                    'spd_layout cannot install its recovery controller because user is already mapped'
                );
            }
            $app->controllerMap['user'] = RecoveryController::class;
        });
    }

    /**
     * Keep authentication and CSRF cookies safe when the deployment is HTTPS.
     *
     * Antragsgrün already defaults to HttpOnly/Lax, but the upstream web
     * config only adds `secure` when the application sees the proxy's request
     * scheme. The production proxy terminates TLS, so APP_PROTOCOL is the
     * explicit deployment boundary for this flag.
     */
    public static function getSessionCookieSettings(): array
    {
        return [
            'httpOnly' => true,
            'sameSite' => Cookie::SAME_SITE_LAX,
            'secure' => strtolower((string)getenv('APP_PROTOCOL')) === 'https',
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
     * Use Antragsgrün's supported consultation-settings seam for layout
     * customization. This keeps title formatting server-side and avoids a
     * client-side document.title correction.
     *
     * @return class-string<ConsultationSettings>
     */
    public static function getConsultationSettingsClass(Consultation $consultation): string
    {
        return ConsultationSettings::class;
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
                'preview' => $thumbBase . '/layout-preview-spd.png',
                'bundle'  => Assets::class,
                'hooks'   => LayoutHooks::class,
            ],
        ];
    }
}
