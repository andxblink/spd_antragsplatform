<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use yii\web\AssetBundle;

/**
 * The layout picker's thumbnail, registered separately from the stylesheet.
 *
 * A separate bundle registration because the appearance admin needs the image URL without
 * registering the whole layout stylesheet on that page, which would restyle the
 * admin screen it is being previewed on. Both bundles deliberately publish the
 * same source directory. Production's CDN hash callback maps every directory
 * inside one plugin to the same destination, so publishing different source
 * directories made the first registered bundle hide the second one's files.
 * One shared source tree is complete whichever registration runs first.
 */
class ThumbnailAssets extends AssetBundle
{
    public $sourcePath = '@app/plugins/spd_layout/assets/';
}
