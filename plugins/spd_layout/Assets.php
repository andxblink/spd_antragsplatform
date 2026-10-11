<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use app\plugins\modern_layout\SharedAssets;
use app\plugins\modern_layout\VersionedAssetBundle;

/**
 * The SPD stylesheet: the Modern partial compiled with SPD's identity tokens.
 * The icon font and the layout script come from Modern's SharedAssets.
 *
 * Yii publishes sourcePath into web/assets/<hash>/, so every relative URL in
 * the compiled CSS resolves from there. That is why the SCSS points font and
 * image paths at ../../fonts/ and ../../img/ : two levels up from the
 * published directory is web/.
 */
class Assets extends VersionedAssetBundle
{
    public $sourcePath = '@app/plugins/spd_layout/assets/';

    public $css = [
        'layout-spd_layout.css',
    ];

    public $depends = [
        SharedAssets::class,
    ];

    protected function sourceDirectory(): string
    {
        return __DIR__ . '/assets';
    }
}
