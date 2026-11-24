<?php

use migrations\AbstractMatecatMigration;

/**
 * ModernMT Full is discontinued. Every project template whose MT engine is a ModernMT Full engine
 * falls back to ModernMT Lite, the MyMemory engine (id 1), with no extra settings: MyMemory takes
 * none, so the ModernMT ones (glossaries, context analyzer) would fail the template validation.
 *
 * The engines are matched by class_load, never by name: a user can rename an engine. The template
 * stores the id as an integer or as a string, and `mt` can be NULL or not JSON at all, so the id
 * is read only from valid JSON and compared unquoted.
 *
 * One-way: the previous engine id is not kept, and the engine itself is deactivated by
 * 20261124120200_deactivate_mmt_engines.
 */
class MigrateMmtProjectTemplatesToMymemory extends AbstractMatecatMigration
{
    public $sql_up = [
        <<<'SQL'
        UPDATE project_templates t
        JOIN engines e
            ON e.id = CASE WHEN JSON_VALID(t.mt) THEN JSON_UNQUOTE(JSON_EXTRACT(t.mt, '$.id')) END
        SET t.mt = '{"id":1,"extra":{}}'
        WHERE e.class_load IN ('MMT', 'Utils\\Engines\\MMT');
        SQL,
    ];

    public $sql_down = [];
}
