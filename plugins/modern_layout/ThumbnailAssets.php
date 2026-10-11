<?php

declare(strict_types=1);

namespace app\plugins\modern_layout;

use yii\web\AssetBundle;

/**
 * The layout picker's thumbnail, published separately from the stylesheet so
 * the appearance admin can show it without restyling itself. The same pattern
 * as plugins/green_layout and plugins/gruen_ci.
 */
class ThumbnailAssets extends AssetBundle
{
    public $sourcePath = '@app/plugins/modern_layout/assets/thumbnails/';
}
