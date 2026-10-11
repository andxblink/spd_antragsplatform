<?php

declare(strict_types=1);

namespace app\plugins\modern_layout;

use app\models\layoutHooks\Hooks;
use app\plugins\ModuleBase;
use yii\web\View;

/**
 * Modern layout: a navigation rail, a top bar with search and card-based
 * content in the shadcn/ui visual language, over upstream's own markup.
 *
 * Registered as a selectable layout, like plugins/green_layout. Enabling this
 * plugin is what makes "Modern" appear under the appearance settings. A
 * branded variant (a separate plugin extending these classes) does not need
 * this plugin enabled: it uses the classes and shared assets directly and
 * ships its own message catalogue.
 */
class Module extends ModuleBase
{
    /**
     * @return array<string, array{title: string, preview: string|null, bundle: class-string, hooks?: class-string<Hooks>, odtTemplate?: string}>
     */
    public static function getProvidedLayouts(?View $view = null): array
    {
        $thumbBase = $view ? ThumbnailAssets::register($view)->baseUrl : '';

        return [
            'std' => [
                'title'   => 'Modern',
                'preview' => $thumbBase . '/layout-preview-modern.png',
                'bundle'  => Assets::class,
                'hooks'   => LayoutHooks::class,
            ],
        ];
    }
}
