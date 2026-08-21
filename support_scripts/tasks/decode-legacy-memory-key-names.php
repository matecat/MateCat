<?php

/**
 * Backfill for `memory_keys`.`key_name`.
 *
 * The old write path ran `FILTER_SANITIZE_SPECIAL_CHARS` over the name, so a resource called
 * "O'Brien" is stored as "O&#39;Brien". A `html_entity_decode` in `TmKeyManagementController` used to
 * undo it on the way out; that call has been removed, because with names stored as typed it turned a
 * resource genuinely called "A &amp; B" into "A & B" for good. The rows written before the change
 * have to be decoded once, in place, or they will be shown with their entities visible.
 *
 * Deliberately NOT a migration. A migration is emitted as flat SQL and applied in one statement at
 * deploy time, which for this would mean `UPDATE memory_keys ... WHERE key_name LIKE '%&#%'` — a full
 * table scan in a single transaction, holding row locks and feeding one large event to the replica,
 * with no way to pause it. This walks the primary key in batches instead, commits each batch, and can
 * be stopped and resumed at any point.
 *
 * `--before` is what makes it safe to run more than once, and it is required. Decoding is not a
 * fixed point: "&#38;#60;" is the encoding of the six characters "&#60;", so its correct decoding
 * still looks encoded and a second pass would turn it into "<". Nothing in the value distinguishes
 * the two, so the bound is *when the row was written* instead — `update_date` older than the deploy
 * that removed the read-time decode. A row this script writes moves its own `update_date` to now and
 * is out of scope from then on, and a name somebody types as "&#39;" after the deploy is never in
 * scope at all.
 *
 * Usage:
 *   php decode-legacy-memory-key-names.php --before='2026-08-21 00:00:00'
 *   php decode-legacy-memory-key-names.php --before='2026-08-21 00:00:00' --apply
 *   php decode-legacy-memory-key-names.php --before='...' --apply --batch=500 --sleep=100000
 *
 *   --before REQUIRED. Only rows whose `update_date` is older than this are touched. Use the
 *            deployment time of the release that removed the read-time decode.
 *   --batch  rows examined per pass (default 1000)
 *   --sleep  microseconds to wait between batches, to keep replication lag down (default 0)
 */

use Utils\Validation\LegacyEntityDecoder;

if (!@include_once __DIR__ . '/../../lib/Bootstrap.php') {
    die("Cannot find lib/Bootstrap.php\n");
}

Bootstrap::start();

$options = getopt('h', ['apply', 'before:', 'batch:', 'sleep:']);

if (array_key_exists('h', $options) || empty($options['before'])) {
    die(
        "Usage: php decode-legacy-memory-key-names.php --before='YYYY-MM-DD HH:MM:SS'"
        . " [--apply] [--batch=N] [--sleep=MICROSECONDS]\n"
        . "--before is required: see the header of this file for why the bound is a date and not a"
        . " pattern.\n"
    );
}

$before = (string)$options['before'];

if (\DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $before) === false) {
    die("--before must be a datetime in 'YYYY-MM-DD HH:MM:SS' form, got: $before\n");
}

$apply = array_key_exists('apply', $options);
$batch = max(1, (int)($options['batch'] ?? 1000));
$sleep = max(0, (int)($options['sleep'] ?? 0));

$db = Bootstrap::getDatabase();
$db->connect();
$conn = $db->getConnection();

echo($apply ? "Applying" : "Dry run (pass --apply to write)")
    . ", rows last written before $before, batch of $batch\n";

// Keyed on the primary key (uid, key_value) rather than on an OFFSET, so each pass is a range scan
// and a row inserted while this runs cannot shift the window and make the script skip a row.
$select = $conn->prepare(
    'SELECT uid, key_value, key_name
       FROM memory_keys
      WHERE (uid, key_value) > (:uid, :key_value)
        AND key_name LIKE :pattern
        AND update_date < :before
      ORDER BY uid, key_value
      LIMIT ' . $batch
);

$update = $conn->prepare(
    'UPDATE memory_keys
        SET key_name = :key_name
      WHERE uid = :uid AND key_value = :key_value'
);

$lastUid = 0;
$lastKey = '';
$examined = 0;
$changed = 0;

while (true) {
    $select->execute([
        'uid'       => $lastUid,
        'key_value' => $lastKey,
        'pattern'   => '%&#%',
        'before'    => $before,
    ]);
    $rows = $select->fetchAll(PDO::FETCH_ASSOC);

    if ($rows === []) {
        break;
    }

    // One transaction per batch: short enough not to hold locks, and it means a run interrupted
    // halfway leaves whole batches behind rather than a half-written one.
    if ($apply) {
        $conn->beginTransaction();
    }

    foreach ($rows as $row) {
        $examined++;
        $lastUid = (int)$row['uid'];
        $lastKey = (string)$row['key_value'];

        $name = (string)$row['key_name'];
        $decoded = LegacyEntityDecoder::decode($name);

        if ($decoded === $name) {
            continue;
        }

        $changed++;

        if ($apply) {
            $update->execute(['key_name' => $decoded, 'uid' => $lastUid, 'key_value' => $lastKey]);
        } else {
            echo "  {$lastUid}/{$lastKey}: " . $name . '  ->  ' . $decoded . "\n";
        }
    }

    if ($apply) {
        $conn->commit();
    }

    echo "examined $examined, " . ($apply ? 'decoded' : 'would decode') . " $changed\n";

    if ($sleep > 0) {
        usleep($sleep);
    }
}

echo "done: examined $examined, " . ($apply ? 'decoded' : 'would decode') . " $changed\n";
