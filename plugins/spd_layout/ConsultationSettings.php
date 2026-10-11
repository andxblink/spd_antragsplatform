<?php

declare(strict_types=1);

namespace app\plugins\spd_layout;

use app\models\settings\Consultation;

/**
 * Consultation settings adapter for the supported specialized-layout seam.
 */
class ConsultationSettings extends Consultation
{
    public function getSpecializedLayoutClass(): ?string
    {
        return LayoutSettings::class;
    }
}
