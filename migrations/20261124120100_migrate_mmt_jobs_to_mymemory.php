<?php

use migrations\AbstractMatecatMigration;

/**
 * ModernMT Full is discontinued. Every job translated with a ModernMT Full engine moves to
 * ModernMT Lite, the MyMemory engine (id 1). Without this, the engine class is gone and the job
 * reads its engine as NONE: no MT suggestion in the editor, and no contribution sent to an MT.
 *
 * `jobs`.`id_mt_engine` has no index, so this is a full scan of the table: run it in a
 * maintenance window. The engines are matched by class_load, never by name.
 *
 * One-way: the previous engine id is not kept.
 */
class MigrateMmtJobsToMymemory extends AbstractMatecatMigration
{
    public $sql_up = [
        <<<'SQL'
        UPDATE jobs
        SET id_mt_engine = 1
        WHERE id_mt_engine IN (
            SELECT id FROM engines WHERE class_load IN ('MMT', 'Utils\\Engines\\MMT')
        );
        SQL,
    ];

    public $sql_down = [];
}
