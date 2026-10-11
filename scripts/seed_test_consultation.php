<?php
/**
 * Seed an isolated test consultation with motions, amendments and comments.
 *
 * Isolated on purpose: everything lands in its own consultation so the live
 * /std one stays clean and cleanup is deleting one row tree, not hunting
 * dozens. Re-runnable only with explicit fixture and confirmation flags. It
 * refuses to run outside the test environment and wraps teardown plus seeding
 * in one transaction.
 *
 * Run: docker compose exec -T web php scripts/seed_test_consultation.php \
 *   --fixture --confirm
 */

$options = getopt('', ['fixture', 'dry-run', 'confirm']);
if (!array_key_exists('fixture', $options)) {
    fwrite(STDERR, "Refusing to seed without --fixture.\n");
    exit(2);
}
if ((string)(getenv('YII_ENV') ?: 'test') !== 'test') {
    fwrite(STDERR, "Refusing to seed outside YII_ENV=test.\n");
    exit(2);
}
if (getenv('ANTRAGSGRUEN_FIXTURE_MODE') !== '1') {
    fwrite(STDERR, "Refusing to seed without ANTRAGSGRUEN_FIXTURE_MODE=1.\n");
    exit(2);
}
$dryRun = array_key_exists('dry-run', $options);
$confirm = array_key_exists('confirm', $options);

define('YII_DEBUG', false);
define('YII_ENV', 'test');
defined('STDIN') or define('STDIN', fopen('php://stdin', 'r'));
defined('STDOUT') or define('STDOUT', fopen('php://stdout', 'w'));

$root = '/var/www/html';
require($root . '/vendor/autoload.php');
require($root . '/vendor/yiisoft/yii2/Yii.php');
$config = require($root . '/config/console.php');
new yii\console\Application($config);

// Yii promotes every PHP notice/deprecation to an ErrorException. Several of
// these legacy models call mb_strlen() on nullable columns, which is harmless
// but fatal under that handler, so it is stood down for this script only.
Yii::$app->errorHandler->unregister();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

use app\models\db\{Amendment, AmendmentSection, AmendmentSupporter, Consultation, ConsultationMotionType,
    ConsultationSettingsMotionSection, IComment, IMotion, ISupporter, Motion, MotionComment, MotionSection,
    MotionSupporter, Site};

const URL_PATH = 'test';

function out(string $s): void { echo $s . "\n"; }

$site = Site::findOne(['id' => 1]);
$template = Consultation::findOne(['id' => 1]);
if (!$site || !$template) { out('FATAL: site or template consultation missing'); exit(1); }

$existingFixtures = [];
foreach ($site->consultations as $c) {
    if (mb_strtolower($c->urlPath) === URL_PATH) {
        $existingFixtures[] = (int)$c->id;
    }
}
if ($dryRun) {
    out('dry-run: fixture consultation ids=' . ($existingFixtures ? implode(',', $existingFixtures) : 'none'));
    exit(0);
}
if ($existingFixtures !== [] && !$confirm) {
    out('Refusing to replace existing fixture without --confirm: ' . implode(',', $existingFixtures));
    exit(2);
}

// Teardown first, so the script is re-runnable and so a half-finished run
// never leaves a stub behind. Order matters: children before parents.
$db = Yii::$app->db;
$transaction = $db->beginTransaction();
register_shutdown_function(static function () use ($transaction): void {
    if ($transaction->getIsActive()) {
        $transaction->rollBack();
    }
});
foreach ($site->consultations as $c) {
    if (mb_strtolower($c->urlPath) !== URL_PATH) { continue; }
    $cid = (int)$c->id;
    out('teardown: removing existing consultation id=' . $cid);
    $db->createCommand("DELETE ms FROM motionSection ms JOIN motion m ON m.id = ms.motionId WHERE m.consultationId = $cid")->execute();
    $db->createCommand("DELETE sp FROM motionSupporter sp JOIN motion m ON m.id = sp.motionId WHERE m.consultationId = $cid")->execute();
    $db->createCommand("DELETE co FROM motionComment co JOIN motion m ON m.id = co.motionId WHERE m.consultationId = $cid")->execute();
    $db->createCommand("DELETE asec FROM amendmentSection asec JOIN amendment a ON a.id = asec.amendmentId JOIN motion m ON m.id = a.motionId WHERE m.consultationId = $cid")->execute();
    $db->createCommand("DELETE asup FROM amendmentSupporter asup JOIN amendment a ON a.id = asup.amendmentId JOIN motion m ON m.id = a.motionId WHERE m.consultationId = $cid")->execute();
    $db->createCommand("DELETE a FROM amendment a JOIN motion m ON m.id = a.motionId WHERE m.consultationId = $cid")->execute();
    $db->createCommand("DELETE FROM motion WHERE consultationId = $cid")->execute();
    $db->createCommand("DELETE cs FROM consultationSettingsMotionSection cs JOIN consultationMotionType t ON t.id = cs.motionTypeId WHERE t.consultationId = $cid")->execute();
    $db->createCommand("DELETE FROM consultationMotionType WHERE consultationId = $cid")->execute();
    $db->createCommand("DELETE FROM consultationUserGroup WHERE consultationId = $cid")->execute();
    $db->createCommand("DELETE FROM consultation WHERE id = $cid")->execute();
}

// ---------------------------------------------------------------- consultation
$con = new Consultation();
$con->siteId = $site->id;
$con->urlPath = URL_PATH;
$con->title = 'Testveranstaltung (Layout & Projektor)';
$con->titleShort = 'Testveranstaltung';
$con->wordingBase = $template->wordingBase;
$con->amendmentNumbering = $template->amendmentNumbering;
$con->adminEmail = $template->adminEmail;
$con->dateCreation = date('Y-m-d H:i:s');
$con->settings = $template->settings;
if (!$con->save()) { out('FATAL consultation: ' . json_encode($con->getErrors())); exit(1); }
out('consultation id=' . $con->id . ' /' . URL_PATH);

// AmendmentSupporter::onSaved() resolves its amendment through
// Consultation::getCurrent(), which is null outside a web request and made the
// save fatal on the afterInsert hook. Console scripts have to set it themselves.
$con->refresh();
Consultation::setCurrent($con);

// -------------------------------------------------------------- motion type
$tplType = $template->motionTypes[0];
$type = new ConsultationMotionType();
$type->setAttributes($tplType->getAttributes(), false);
$type->id = null;
$type->consultationId = $con->id;
$type->motionPrefix = $type->motionPrefix ?? '';
// save(false): these rows are clones of a valid row, and several legacy
// columns are nullable in the schema but non-null in the validators, which
// turns a deprecation into a fatal on the way through.
if (!$type->save(false)) {
    throw new RuntimeException('motionType: ' . json_encode($type->getErrors()));
}

$sectionIds = [];
foreach ($tplType->motionSections as $tplSection) {
    $s = new ConsultationSettingsMotionSection();
    $s->setAttributes($tplSection->getAttributes(), false);
    $s->id = null;
    $s->motionTypeId = $type->id;
    if (!$s->save(false)) {
        throw new RuntimeException('section: ' . json_encode($s->getErrors()));
    }
    $sectionIds[$tplSection->type . '_' . $tplSection->position] = $s->id;
    $sectionIds['pos' . $tplSection->position] = $s->id;
}
out('motionType id=' . $type->id . ' sections=' . implode(',', array_values(array_unique($sectionIds))));

$SEC_TITLE = $sectionIds['pos0'];
$SEC_TEXT  = $sectionIds['pos1'];
$SEC_REASON = $sectionIds['pos2'];

// ------------------------------------------------------------------- content
$motions = [
    ['Bezahlbaren Wohnraum in Kreuzberg sichern',
     '<p>Der Kreisverband fordert den Senat auf, den Ankauf von Bestandswohnungen durch landeseigene Wohnungsbaugesellschaften deutlich auszuweiten.</p><p>Insbesondere in Milieuschutzgebieten soll das Vorkaufsrecht konsequent ausgeübt werden.</p><p>Die dafür notwendigen Mittel sind im Doppelhaushalt einzustellen.</p>',
     '<p>Die Mieten in Friedrichshain-Kreuzberg sind in den vergangenen Jahren erheblich gestiegen. Das Vorkaufsrecht ist das wirksamste Instrument, das dem Bezirk zur Verfügung steht.</p>', 'Anna Schulz'],
    ['Verkehrswende im Bezirk beschleunigen',
     '<p>Die Umsetzung geschützter Radfahrstreifen an Hauptverkehrsstraßen ist zu beschleunigen.</p><p>Bis Ende 2027 sollen alle im Radverkehrsplan vorgesehenen Abschnitte fertiggestellt sein.</p>',
     '<p>Der Radverkehrsplan liegt seit Jahren vor. An der Umsetzung hakt es vor allem an der Personalausstattung der Straßenverkehrsbehörde.</p>', 'Mehmet Kaya'],
    ['Kitaplätze bedarfsgerecht ausbauen',
     '<p>Der Bezirk wird aufgefordert, gemeinsam mit freien Trägern jährlich mindestens 200 zusätzliche Kitaplätze zu schaffen.</p><p>Die Personalgewinnung ist durch ein Ausbildungsprogramm zu unterstützen.</p>',
     '<p>Der Bedarf übersteigt das Angebot seit Jahren deutlich. Junge Familien verlassen den Bezirk auch aus diesem Grund.</p>', 'Julia Berger'],
    ['Energetische Sanierung sozial gerecht gestalten',
     '<p>Bei der energetischen Sanierung kommunaler Bestände ist sicherzustellen, dass die Modernisierungsumlage nicht zu Verdrängung führt.</p><p>Für Haushalte mit geringem Einkommen ist ein Härtefallfonds einzurichten.</p>',
     '<p>Klimaschutz und Mieterschutz dürfen nicht gegeneinander ausgespielt werden.</p>', 'Thomas Weber'],
    ['Digitale Teilhabe in Schulen sichern',
     '<p>Alle weiterführenden Schulen im Bezirk sollen bis 2028 mit flächendeckendem WLAN und ausreichend Endgeräten ausgestattet werden.</p>',
     '<p>Die Ausstattung ist zwischen den Schulen sehr ungleich verteilt. Das benachteiligt Kinder aus einkommensschwachen Familien.</p>', 'Sarah Klein'],
    ['Gewerbemieten begrenzen',
     '<p>Die Bundestagsfraktion wird gebeten, sich für einen Gewerbemietspiegel und einen Kündigungsschutz für kleine Gewerbetreibende einzusetzen.</p>',
     '<p>Kiezstrukturen gehen verloren, wenn Handwerk und kleine Läden verdrängt werden.</p>', 'Ali Demir'],
    ['Öffentliche Grünflächen aufwerten',
     '<p>Die Pflege der öffentlichen Grünflächen ist auf ein Niveau anzuheben, das eine ganzjährige Nutzbarkeit sicherstellt.</p><p>Dazu ist das Grünflächenamt personell zu verstärken.</p>',
     '<p>Grünflächen sind für viele Menschen ohne eigenen Balkon der einzige Freiraum.</p>', 'Christina Vogel'],
    ['Beteiligung von Jugendlichen stärken',
     '<p>Der Kreisverband setzt sich für ein Jugendparlament mit eigenem Budget auf Bezirksebene ein.</p>',
     '<p>Jugendliche sind von vielen Entscheidungen betroffen, aber kaum beteiligt.</p>', 'Lukas Hoffmann'],
    ['Gesundheitsversorgung im Kiez erhalten',
     '<p>Der Senat wird aufgefordert, die Niederlassung von Hausärztinnen und Hausärzten in unterversorgten Kiezen gezielt zu fördern.</p>',
     '<p>Die Wartezeiten auf einen Termin sind vielerorts nicht mehr zumutbar.</p>', 'Fatima Yilmaz'],
    ['Transparenz bei Bezirksentscheidungen',
     '<p>Alle Vorlagen der Bezirksverordnetenversammlung sollen spätestens eine Woche vor der Sitzung öffentlich einsehbar sein.</p>',
     '<p>Transparenz ist Voraussetzung für Beteiligung.</p>', 'Jonas Richter'],
];

function mkSection(int $motionId, int $sectionId, string $html): void {
    $s = new MotionSection();
    $s->motionId = $motionId;
    $s->sectionId = $sectionId;
    $s->data = $html;
    $s->dataRaw = '';
    $s->cache = '';
    $s->public = 1;
    if (!$s->save(false)) {
        throw new RuntimeException('section: ' . json_encode($s->getErrors()));
    }
}

$created = [];
foreach ($motions as $i => [$title, $text, $reason, $initiator]) {
    $m = new Motion();
    $m->consultationId = $con->id;
    $m->motionTypeId = $type->id;
    $m->title = $title;
    $m->titlePrefix = 'A' . ($i + 1);
    $m->status = IMotion::STATUS_SUBMITTED_SCREENED;
    $m->cache = '';
    $m->dateCreation = date('Y-m-d H:i:s', time() - (86400 * (10 - $i)));
    $m->dateSubmission = $m->dateCreation;
    $m->datePublication = $m->dateCreation;
    $m->dateContentModification = $m->dateCreation;
    $m->statusString = '';
    $m->slug = 'a' . ($i + 1) . '-' . substr(md5($title), 0, 5);
    if (!$m->save(false)) {
        throw new RuntimeException('motion: ' . json_encode($m->getErrors()));
    }

    mkSection($m->id, $SEC_TITLE, $title);
    mkSection($m->id, $SEC_TEXT, $text);
    mkSection($m->id, $SEC_REASON, $reason);

    $sup = new MotionSupporter();
    $sup->motionId = $m->id;
    $sup->role = ISupporter::ROLE_INITIATOR;
    $sup->position = 0;
    $sup->personType = ISupporter::PERSON_NATURAL;
    $sup->name = $initiator;
    $sup->dateCreation = $m->dateCreation;
    if (!$sup->save(false)) {
        throw new RuntimeException('supporter: ' . json_encode($sup->getErrors()));
    }

    $created[] = [$m, $text];
    out('motion A' . ($i + 1) . ' id=' . $m->id . ' "' . mb_substr($title, 0, 40) . '"');
}

// ----------------------------------------------------------------- amendments
// Real edits to the motion text, so the projector renders a genuine diff.
$edits = [
    0 => ['<p>Der Kreisverband fordert den Senat auf, den Ankauf von Bestandswohnungen durch landeseigene Wohnungsbaugesellschaften <strong>unverzüglich und deutlich</strong> auszuweiten.</p><p>Insbesondere in Milieuschutzgebieten soll das Vorkaufsrecht konsequent ausgeübt werden.</p><p>Die dafür notwendigen Mittel sind im Doppelhaushalt einzustellen.</p>', 'Anna Schulz', 'Ergänzung um eine Fristsetzung.'],
    1 => ['<p>Die Umsetzung geschützter Radfahrstreifen an Hauptverkehrsstraßen ist zu beschleunigen.</p><p>Bis Ende 2026 sollen alle im Radverkehrsplan vorgesehenen Abschnitte fertiggestellt sein.</p>', 'Mehmet Kaya', 'Ambitioniertere Frist.'],
    2 => ['<p>Der Bezirk wird aufgefordert, gemeinsam mit freien Trägern jährlich mindestens 300 zusätzliche Kitaplätze zu schaffen.</p><p>Die Personalgewinnung ist durch ein Ausbildungsprogramm zu unterstützen.</p>', 'Julia Berger', 'Zahl an den tatsächlichen Bedarf angepasst.'],
    3 => ['<p>Bei der energetischen Sanierung kommunaler Bestände ist sicherzustellen, dass die Modernisierungsumlage nicht zu Verdrängung führt.</p>', 'Thomas Weber', 'Streichung des zweiten Absatzes.'],
];

$amCount = 0;
foreach ($edits as $idx => [$newText, $initiator, $explanation]) {
    if (!isset($created[$idx])) { continue; }
    /** @var Motion $m */
    $m = $created[$idx][0];
    $a = new Amendment();
    $a->motionId = $m->id;
    $a->titlePrefix = $m->titlePrefix . '-Ä1';
    $a->status = IMotion::STATUS_SUBMITTED_SCREENED;
    $a->changeEditorial = '';
    $a->changeText = '';
    $a->changeExplanation = $explanation;
    $a->changeExplanationHtml = 0;
    $a->cache = '';
    $a->statusString = '';
    $a->dateCreation = date('Y-m-d H:i:s', time() - 86400);
    $a->dateSubmission = $a->dateCreation;
    $a->datePublication = $a->dateCreation;
    $a->dateContentModification = $a->dateCreation;
    if (!$a->save(false)) {
        throw new RuntimeException('amendment: ' . json_encode($a->getErrors()));
    }

    // Only the changed section is stored; the diff is computed against the motion.
    $as = new AmendmentSection();
    $as->amendmentId = $a->id;
    $as->sectionId = $SEC_TEXT;
    $as->data = $newText;
    $as->dataRaw = '';
    $as->cache = '';
    $as->public = 1;
    if (!$as->save(false)) {
        throw new RuntimeException('amendmentSection: ' . json_encode($as->getErrors()));
    }

    $asup = new AmendmentSupporter();
    $asup->amendmentId = $a->id;
    $asup->role = ISupporter::ROLE_INITIATOR;
    $asup->position = 0;
    $asup->personType = ISupporter::PERSON_NATURAL;
    $asup->name = $initiator;
    $asup->dateCreation = $a->dateCreation;
    if (!$asup->save(false)) {
        throw new RuntimeException('amendmentSupporter: ' . json_encode($asup->getErrors()));
    }

    $amCount++;
    out('amendment ' . $a->titlePrefix . ' id=' . $a->id . ' on motion ' . $m->id);
}

// ------------------------------------------------------------------- comments
$comments = [
    [0, 1, 'Björn Lehmann', 'Der Vorkauf ist richtig, aber ohne zusätzliches Personal im Bezirksamt bleibt es Symbolpolitik.'],
    [0, 2, 'Nadine Fischer', 'Zustimmung. Wir sollten die Zahl der Ankäufe pro Jahr konkret benennen.'],
    [1, 1, 'Erik Sommer', 'Bitte auch die Kreuzungen mitdenken, dort passieren die meisten Unfälle.'],
    [2, 1, 'Miriam Stein', 'Die 200 Plätze halte ich für zu niedrig gegriffen.'],
    [4, 1, 'Paul Neumann', 'Endgeräte allein reichen nicht, es braucht auch Wartung und Support.'],
    [6, 1, 'Hannah Groß', 'Gilt das auch für die Spielplätze?'],
];

$coCount = 0;
foreach ($comments as [$idx, $para, $name, $text]) {
    if (!isset($created[$idx])) { continue; }
    /** @var Motion $m */
    $m = $created[$idx][0];
    $c = new MotionComment();
    $c->motionId = $m->id;
    $c->sectionId = $SEC_TEXT;
    $c->paragraph = $para;
    $c->name = $name;
    $c->text = $text;
    $c->status = IComment::STATUS_VISIBLE;
    $c->dateCreation = date('Y-m-d H:i:s', time() - rand(3600, 172800));
    if (!$c->save(false)) {
        throw new RuntimeException('comment: ' . json_encode($c->getErrors()));
    }
    $coCount++;
}
out('comments: ' . $coCount);

out('');
out('DONE  consultation=' . $con->id . '  motions=' . count($created) . '  amendments=' . $amCount . '  comments=' . $coCount);
out('URL: /' . URL_PATH);
$transaction->commit();
