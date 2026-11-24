<?php

use migrations\AbstractMatecatMigration;

/**
 * ModernMT Full is discontinued and its engine class is removed.
 *
 * Every ModernMT Full engine is deactivated, not deleted: jobs, projects and user metadata still
 * reference the ids. A Lara engine could carry an optional ModernMT license, which mirrored its
 * memory writes to ModernMT; the license is dropped from it. ModernMT Lite (MyMemory, id 1) loses
 * the sentence of its description that sold the Full plan.
 *
 * Runs after the template and job migrations, which match the same engines by class_load and so
 * do not depend on `active`.
 *
 * One-way: re-activating every ModernMT engine on rollback would also revive the ones their
 * owners had disabled, and the stripped licenses are not kept.
 */
class DeactivateMmtEngines extends AbstractMatecatMigration
{
    public $sql_up = [
        <<<'SQL'
        UPDATE engines
        SET active = 0
        WHERE class_load IN ('MMT', 'Utils\\Engines\\MMT')
          AND active = 1;
        SQL,
        <<<'SQL'
        UPDATE engines
        SET extra_parameters = JSON_REMOVE(extra_parameters, '$."MMT-License"')
        WHERE class_load IN ('Lara', 'Utils\\Engines\\Lara')
          AND (CASE WHEN JSON_VALID(extra_parameters)
                    THEN JSON_CONTAINS_PATH(extra_parameters, 'one', '$."MMT-License"')
                    ELSE 0 END) = 1;
        SQL,
        // ModernMT Lite stays, but its description pointed at the pricing of the discontinued Full.
        <<<'SQL'
        UPDATE engines
        SET description = 'Smart machine translation that learns from your corrections for enhanced quality and productivity thanks to ModernMT’s basic features.'
        WHERE id = 1
          AND class_load = 'MyMemory'
          AND description LIKE '%modernmt.com/pricing%';
        SQL,
    ];

    public $sql_down = [];
}
