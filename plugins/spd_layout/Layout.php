<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

/**
 * Server-rendered metadata for the SPD layout.
 */
class Layout extends \app\models\settings\Layout
{
    public function formatTitle(string $title): string
    {
        return $title === '' ? 'Antragsplatform' : $title;
    }
}
