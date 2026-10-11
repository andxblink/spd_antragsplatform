<?php

declare(strict_types=1);

namespace app\plugins\modern_layout;

use yii\web\AssetBundle;

/**
 * An asset bundle whose CSS URLs change when the files do.
 *
 * Yii derives the published directory (web/assets/<hash>/) from the source
 * PATH, not the file contents. Editing a stylesheet therefore keeps the exact
 * same URL, and every browser that has already seen the old file serves it
 * from cache indefinitely. That happened during development and would happen
 * to every user on every deploy.
 *
 * Appending each file's mtime gives the URL a new query string whenever the
 * file actually changes, and leaves it stable when it does not. It is needed
 * rather than redundant: `config/web.php` turns the asset manager's own
 * `appendTimestamp` off for web requests.
 */
abstract class VersionedAssetBundle extends AssetBundle
{
    /**
     * The bundle's source directory as a real path. Subclasses point
     * `$sourcePath` at a directory inside their plugin.
     */
    abstract protected function sourceDirectory(): string;

    public function init(): void
    {
        parent::init();

        $this->css = array_map(function (mixed $file): mixed {
            if (!is_string($file)) {
                return $file;
            }
            $path = $this->sourceDirectory() . '/' . $file;

            return file_exists($path) ? $file . '?v=' . filemtime($path) : $file;
        }, $this->css);
    }
}
