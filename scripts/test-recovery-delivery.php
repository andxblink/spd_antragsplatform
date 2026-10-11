<?php

declare(strict_types=1);

/**
 * Fixture-backed tests for the narrow upstream recovery compatibility seam.
 * These cases deliberately avoid Yii, a database, and real mail delivery.
 */

require dirname(__DIR__) . '/plugins/spd_layout/RecoveryDeliveryResult.php';

$cases = [
    [
        'name' => 'translated marker in a scalar flash',
        'flash' => 'E-Mail konnte nicht versendet werden: transport offline',
        'marker' => 'E-Mail konnte nicht versendet werden',
        'expected' => true,
    ],
    [
        'name' => 'marker in one item of an array flash',
        'flash' => ['unrelated message', 'Mailversand fehlgeschlagen'],
        'marker' => 'Mailversand fehlgeschlagen',
        'expected' => true,
    ],
    [
        'name' => 'unrelated flash is not a delivery failure',
        'flash' => 'Die E-Mail-Adresse ist ungültig',
        'marker' => 'E-Mail konnte nicht versendet werden',
        'expected' => false,
    ],
    [
        'name' => 'null flash is not a delivery failure',
        'flash' => null,
        'marker' => 'Mailversand fehlgeschlagen',
        'expected' => false,
    ],
    [
        'name' => 'empty marker cannot match arbitrary content',
        'flash' => 'anything',
        'marker' => '',
        'expected' => false,
    ],
];

foreach ($cases as $case) {
    $actual = \app\plugins\spd_layout\RecoveryDeliveryResult::reportsFailure(
        $case['flash'],
        $case['marker']
    );
    if ($actual !== $case['expected']) {
        throw new RuntimeException($case['name'] . ': unexpected result');
    }
}

echo "recovery delivery fixtures: ok\n";
