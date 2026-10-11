<?php

declare(strict_types=1);

/**
 * Exercise the pure status seam without booting Yii or constructing an
 * ActiveRecord. The seam returns message keys and values, never prose, so
 * the expectations here are language-independent. The fixture deliberately carries legal-record fields that the
 * seam must not receive or rewrite.
 */

$root = dirname(__DIR__);
$fixturePath = $root . '/scripts/fixtures/status-presentation.json';
$fixtures = json_decode((string)file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($fixtures) || $fixtures === []) {
    throw new RuntimeException('status presentation fixture is empty');
}

eval(<<<'PHP'
namespace app\models\db;
abstract class IMotion
{
    public const STATUS_WITHDRAWN = -1;
    public const STATUS_DRAFT = 1;
    public const STATUS_SUBMITTED_UNSCREENED = 2;
    public const STATUS_SUBMITTED_SCREENED = 3;
    public const STATUS_ACCEPTED = 4;
    public const STATUS_REJECTED = 5;
    public const STATUS_MODIFIED_ACCEPTED = 6;
    public const STATUS_COMPLETED = 9;
    public const STATUS_REFERRED = 10;
    public const STATUS_COLLECTING_SUPPORTERS = 15;
    public const STATUS_DRAFT_ADMIN = 16;
    public const STATUS_PROCESSED = 17;
    public const STATUS_SUBMITTED_UNSCREENED_CHECKED = 18;
    public const STATUS_MERGING_DRAFT_PUBLIC = 19;
    public const STATUS_MERGING_DRAFT_PRIVATE = 20;
    public const STATUS_CUSTOM_STRING = 23;
    public const STATUS_ADOPTED = 8;
    public const STATUS_WITHDRAWN_INVISIBLE = -3;
}
PHP);

eval(<<<'PHP'
namespace app\components;
final class Tools
{
    public static function formatMysqlDate(?string $date, bool $allowRelativeDates = true): string
    {
        return (string)$date;
    }

    public static function formatMysqlDateTime(?string $date, bool $allowRelativeDates = true): string
    {
        return (string)$date;
    }
}
PHP);

require $root . '/plugins/modern_layout/StatusPresentation.php';

foreach ($fixtures as $fixture) {
    $name = (string)$fixture['name'];
    $kind = \app\plugins\modern_layout\StatusPresentation::kind((int)$fixture['status']);
    if ($kind !== $fixture['expected_kind']) {
        throw new RuntimeException($name . ': unexpected status kind');
    }

    $date = \app\plugins\modern_layout\StatusPresentation::dateLine(
        (int)$fixture['status'],
        $fixture['resolution'],
        $fixture['submission'],
        $fixture['creation']
    );
    if ($date !== $fixture['expected_date']) {
        throw new RuntimeException($name . ': unexpected date line');
    }

    foreach (['text', 'line_numbers', 'authors', 'votes', 'publication_history'] as $field) {
        if (!array_key_exists($field, $fixture['legal_record'])) {
            throw new RuntimeException($name . ': incomplete legal-record fixture');
        }
    }
}

echo "status presentation fixtures: ok\n";
