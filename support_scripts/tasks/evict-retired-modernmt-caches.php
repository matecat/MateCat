<?php

/**
 * Run once, right after the three ModernMT Full removal migrations (20261124120000,
 * 20261124120100, 20261124120200).
 *
 * The migrations rewrite project_templates, jobs and engines with plain SQL, so no DAO write evicts
 * the Redis cache in front of them. Without this script a user keeps seeing a template that points
 * at the disabled ModernMT engine for up to a day (the template list and the default template are
 * cached that long), and an engine list that still holds it for five minutes.
 *
 * Only the keys of the affected users are deleted. Never flush the cache database instead: it also
 * holds the login sessions.
 *
 * Idempotent: running it again only deletes keys that rebuild on the next read.
 *
 * Usage: php support_scripts/tasks/evict-retired-modernmt-caches.php [--dry-run]
 */

use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\Projects\ProjectTemplateDao;

$root = realpath(dirname(__FILE__) . '/../../');
include_once $root . "/lib/Bootstrap.php";
Bootstrap::start();

$dryRun = in_array('--dry-run', $argv, true);
$db = Bootstrap::getDatabase();
$conn = $db->getConnection();

// The owners of a ModernMT Full engine are the only users whose templates the migration rewrote: a
// template can only point at an engine its owner holds. Lara engines lost their ModernMT license,
// so their owners' engine lists are stale too.
$engines = $conn->query(
    "SELECT id, uid FROM engines
     WHERE class_load IN ('MMT', 'Utils\\\\Engines\\\\MMT', 'Lara', 'Utils\\\\Engines\\\\Lara')"
)->fetchAll(PDO::FETCH_ASSOC);

$modernMtOwners = array_unique(array_map(
    'intval',
    array_column(
        $conn->query(
            "SELECT DISTINCT uid FROM engines WHERE class_load IN ('MMT', 'Utils\\\\Engines\\\\MMT') AND uid > 0"
        )->fetchAll(PDO::FETCH_ASSOC),
        'uid'
    )
));

$engineOwners = array_unique(array_filter(array_map('intval', array_column($engines, 'uid')), fn(int $uid): bool => $uid > 0));

echo sprintf(
    "%d ModernMT owner(s) to evict templates for, %d engine owner(s) and %d engine(s) to evict engine reads for.%s\n",
    count($modernMtOwners),
    count($engineOwners),
    count($engines),
    $dryRun ? ' Dry run: nothing deleted.' : ''
);

if ($dryRun) {
    exit(0);
}

$templateDao = new ProjectTemplateDao($db);
$engineDao = new EngineDAO($db);

foreach ($modernMtOwners as $uid) {
    $templateDao->destroyCachesByUid($uid);
}

foreach ($engineOwners as $uid) {
    // What EnginesController::listEngines() and EngineController read: a user's active engines.
    $userEngines = EngineStruct::getStruct();
    $userEngines->uid = $uid;
    $userEngines->active = true;
    $engineDao->destroyCache($userEngines);
}

foreach ($engines as $engine) {
    // What EnginesFactory::getInstance() reads: one engine by id.
    $byId = EngineStruct::getStruct();
    $byId->id = (int)$engine['id'];
    $engineDao->destroyCache($byId);
}

echo "Done.\n";
