<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use yii\web\AssetBundle;

/**
 * Yii publishes sourcePath into web/assets/<hash>/, so every relative URL in
 * the compiled CSS resolves from there. That is why the SCSS-controlled font
 * and image paths point at ../../fonts/ and ../../img/: two levels up from the
 * published directory is web/. An inherited, unused Font Awesome declaration
 * hardcodes ../fonts/ and retains upstream's latent 404; this bundle does not
 * claim to repair that upstream path.
 */
class Assets extends AssetBundle
{
    public $sourcePath = '@app/plugins/spd_layout/assets/';

    public $css = [
        'layout-spd_layout.css',
    ];

    public $js = [
        'composition.js',
        'status.js',
        'surfaces.js',
    ];

    public $depends = [];

    /**
     * Yii derives the published directory (web/assets/<hash>/) from the source
     * PATH, not the file contents. Editing the stylesheet therefore keeps the
     * exact same URL, and every browser that has already seen the old file
     * serves it from cache indefinitely. That happened during development and
     * would happen to every user on every deploy.
     *
     * Appending each file's mtime gives the URL a new query string whenever
     * the CSS or a behavior asset changes, and leaves it stable when it does
     * not.
     */
    public function init(): void
    {
        parent::init();

        $source = \Yii::getAlias($this->sourcePath);
        $cssFile = $source . '/layout-spd_layout.css';
        if (file_exists($cssFile)) {
            $this->css = ['layout-spd_layout.css?v=' . filemtime($cssFile)];
        }
        $jsFiles = ['composition.js', 'status.js', 'surfaces.js'];
        $this->js = [];
        foreach ($jsFiles as $jsFile) {
            $path = $source . '/' . $jsFile;
            if (file_exists($path)) {
                $this->js[] = $jsFile . '?v=' . filemtime($path);
            }
        }
    }
}
