<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use app\models\settings\Layout;

/**
 * Keep the upstream title formatter, but remove the upstream product suffix
 * at the supported server-side layout seam.
 */
class LayoutSettings extends Layout
{
    /**
     * Run upstream's formatter first, so anything it adds besides the product
     * suffix keeps arriving, then strip only that suffix. An empty title stays
     * empty instead of becoming the product name.
     */
    public function formatTitle(string $title): string
    {
        if ($title === '') {
            return '';
        }

        $title = parent::formatTitle($title);
        $title = preg_replace('/ \(Antragsgrün\)$/u', '', $title) ?? $title;

        return preg_replace('/, Antragsgrün\)$/u', ')', $title) ?? $title;
    }
}
