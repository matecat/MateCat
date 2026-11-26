<?php

use migrations\AbstractMatecatMigration;

/**
 * ModernMT Full is discontinued. Every job translated with a ModernMT Full engine moves to
 * ModernMT Lite, the MyMemory engine (id 1). Without this, the engine class is gone and the job
 * reads its engine as NONE: no MT suggestion in the editor, and no contribution sent to an MT.
 *
 * `jobs`.`id_mt_engine` has no index, so one table-wide UPDATE would read, and under REPEATABLE
 * READ lock, every row of `jobs` until it ends, holding up every translation save. The update runs
 * instead in ranges of the indexed `id`, about BATCH_SIZE rows each, one short statement apiece.
 * `last_update` is kept as it was: the column is ON UPDATE CURRENT_TIMESTAMP, and an engine swap
 * is not an edit of the job.
 *
 * The engines are matched by class_load, never by name. Without a ModernMT Full engine, which is
 * the case on most installations, nothing is read beyond the engines table.
 *
 * Safe to run again: a second run finds nothing left to move. One-way: the previous engine id is
 * not kept.
 */
class MigrateMmtJobsToMymemory extends AbstractMatecatMigration
{
    public const int BATCH_SIZE = 10000;

    public $sql_up = [];

    public $sql_down = [];

    public function up(): void
    {
        self::moveJobs($this->getAdapter()->getConnection());
    }

    /**
     * Moves every job on a ModernMT Full engine to engine 1 and returns how many rows changed.
     */
    public static function moveJobs(PDO $pdo, int $batchSize = self::BATCH_SIZE): int
    {
        $stmt = $pdo->prepare("SELECT id FROM engines WHERE class_load IN (?, ?)");
        $stmt->execute(['MMT', 'Utils\\Engines\\MMT']);
        $engineIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($engineIds === []) {
            return 0;
        }

        // Each range ends on a real id, read off the `id` index, so a sparse table costs no empty
        // statements: a batch covers about $batchSize rows, whatever the gaps between ids.
        $nextUpperBound = $pdo->prepare(
            "SELECT id FROM jobs WHERE id >= ? ORDER BY id LIMIT 1 OFFSET " . max(0, $batchSize - 1)
        );
        $update = $pdo->prepare(
            "UPDATE jobs
             SET id_mt_engine = 1, last_update = last_update
             WHERE id BETWEEN ? AND ?
               AND id_mt_engine IN (" . implode(', ', array_fill(0, count($engineIds), '?')) . ")"
        );

        $moved = 0;
        $from = (int)$pdo->query("SELECT COALESCE(MIN(id), 0) FROM jobs")->fetchColumn();
        $last = (int)$pdo->query("SELECT COALESCE(MAX(id), -1) FROM jobs")->fetchColumn();

        while ($from <= $last) {
            $nextUpperBound->execute([$from]);
            $to = $nextUpperBound->fetchColumn();
            $nextUpperBound->closeCursor();
            $to = $to === false ? $last : (int)$to;

            $update->execute([$from, $to, ...$engineIds]);
            $moved += $update->rowCount();

            $from = $to + 1;
        }

        return $moved;
    }
}
