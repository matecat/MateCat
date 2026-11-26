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
 * Exits non-zero when the cache cannot be reached, before or after the evictions. A DAO eviction
 * logs a cache failure and carries on, and its `false` also means "nothing was cached", so its
 * return value cannot tell the operator that nothing was deleted: the ping can.
 *
 * Usage: php support_scripts/tasks/evict-retired-modernmt-caches.php [--dry-run]
 */

use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\Projects\ProjectTemplateDao;
use Utils\Redis\RedisHandler;

$root = realpath(dirname(__FILE__) . '/../../');
include_once $root . "/lib/Bootstrap.php";
Bootstrap::start();

$dryRun = in_array('--dry-run', $argv, true);

/**
 * Fails the run when the cache does not answer, so a wrong host never ends in "Done.".
 */
$assertCacheReachable = static function (string $when): void {
    try {
        (new RedisHandler())->getConnection()->ping();
    } catch (Throwable $e) {
        fwrite(STDERR, "The cache cannot be reached $when: " . $e->getMessage() . "\n");
        exit(1);
    }
};

$assertCacheReachable('before the evictions, nothing was deleted');

$db = Bootstrap::getDatabase();
$conn = $db->getConnection();

$modernMtClasses = ['MMT', 'Utils\\Engines\\MMT'];
$laraClasses = ['Lara', 'Utils\\Engines\\Lara'];

// The owners of a ModernMT Full engine are the only users whose templates the migration rewrote: a
// template can only point at an engine its owner holds. Lara engines lost their ModernMT license,
// so their owners' engine lists are stale too.
$stmt = $conn->prepare("SELECT id, uid FROM engines WHERE class_load IN (?, ?, ?, ?)");
$stmt->execute([...$modernMtClasses, ...$laraClasses]);
$engines = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT DISTINCT uid FROM engines WHERE class_load IN (?, ?) AND uid > 0");
$stmt->execute($modernMtClasses);
$modernMtOwners = array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));

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

$assertCacheReachable('after the evictions, some keys may still be there: run the script again');

echo "Done.\n";
