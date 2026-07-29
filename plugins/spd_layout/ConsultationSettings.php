<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

/**
 * Install the SPD-specific server-side layout for every consultation while the
 * plugin is active. This reaches metadata rendered before layout hooks run.
 */
class ConsultationSettings extends \app\models\settings\Consultation
{
    /**
     * @return class-string<\app\models\settings\Layout>
     */
    public function getSpecializedLayoutClass(): string
    {
        return Layout::class;
    }
}
