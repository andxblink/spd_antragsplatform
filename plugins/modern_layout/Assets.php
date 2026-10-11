<?php

declare(strict_types=1);

namespace app\plugins\modern_layout;

/**
 * The neutral Modern stylesheet.
 *
 * Yii publishes sourcePath into web/assets/<hash>/, so every relative URL in
 * the compiled CSS resolves from there. That is why the SCSS points font and
 * image paths at ../../fonts/ and ../../img/ : two levels up from the
 * published directory is web/.
 */
class Assets extends VersionedAssetBundle
{
    public $sourcePath = '@app/plugins/modern_layout/assets/';

    public $css = [
        'layout-modern_layout.css',
    ];

    public $depends = [
        SharedAssets::class,
    ];

    protected function sourceDirectory(): string
    {
        return __DIR__ . '/assets';
    }
}
