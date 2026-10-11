<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$upstream = getenv('ANTRAGSGRUEN_REFERENCE') ?: $root . '/temp/audit-upstream-v4.17.1';

require $upstream . '/vendor/autoload.php';
require $upstream . '/models/settings/JsonConfigTrait.php';
require $upstream . '/models/settings/Consultation.php';
require $upstream . '/models/settings/Layout.php';
require $root . '/plugins/spd_layout/LayoutSettings.php';
require $root . '/plugins/spd_layout/ConsultationSettings.php';

$layout = new \app\plugins\spd_layout\LayoutSettings();
// Inputs are raw titles; upstream's formatter runs first and appends the
// product suffix, which the plugin then strips. A title already ending in a
// bracket gets ", Antragsgrün)" upstream, and must keep its own bracket.
$cases = [
    'SPD Kreuzberg 61' => 'SPD Kreuzberg 61',
    'SPD Kreuzberg 61 (Antragsgrün)' => 'SPD Kreuzberg 61',
    'Antrag A1 (SPD Kreuzberg)' => 'Antrag A1 (SPD Kreuzberg)',
    'Antrag A1 (SPD Kreuzberg, Antragsgrün)' => 'Antrag A1 (SPD Kreuzberg)',
    '' => '',
];

foreach ($cases as $input => $expected) {
    $actual = $layout->formatTitle($input);
    if ($actual !== $expected) {
        fwrite(STDERR, "title formatter mismatch for {$input}: {$actual}\n");
        exit(1);
    }
}

$settings = new \app\plugins\spd_layout\ConsultationSettings([]);
if ($settings->getSpecializedLayoutClass() !== \app\plugins\spd_layout\LayoutSettings::class) {
    fwrite(STDERR, "specialized layout seam is not registered\n");
    exit(1);
}

echo "plugin settings seam: ok\n";
