<?php

declare(strict_types=1);

namespace app\plugins\modern_layout;

/**
 * What every layout built on Modern shares, published once: the icon font
 * with its @font-face, and the DOM-pass script that LayoutHooks::endPage()
 * emits. Layout bundles depend on this one.
 */
class SharedAssets extends VersionedAssetBundle
{
    public $sourcePath = '@app/plugins/modern_layout/assets/shared/';

    public $css = [
        'modern-fonts.css',
    ];

    protected function sourceDirectory(): string
    {
        return __DIR__ . '/assets/shared';
    }
}
