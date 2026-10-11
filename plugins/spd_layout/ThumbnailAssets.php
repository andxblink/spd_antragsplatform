<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use yii\web\AssetBundle;

/**
 * The layout picker's thumbnail, published separately from the stylesheet.
 *
 * A separate bundle because the appearance admin needs the image URL without
 * registering the whole layout stylesheet on that page, which would restyle the
 * admin screen it is being previewed on. This is the pattern
 * plugins/green_layout and plugins/gruen_ci use.
 */
class ThumbnailAssets extends AssetBundle
{
    public $sourcePath = '@app/plugins/spd_layout/assets/thumbnails/';
}
