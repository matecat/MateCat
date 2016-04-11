<?php

class AddIsOriginalToRemoteFiles extends AbstractMatecatMigration {
    public $sql_up = <<<EOF
ALTER TABLE `remote_files` ADD COLUMN `is_original` tinyint(1) DEFAULT 0;
INSERT INTO `remote_files` (
    `id_file`,
    `id_job`,
    `remote_id`,
    `is_original`
)
SELECT
    `id`,
    0,
    `remote_id`,
    1
FROM
    `files`
WHERE
    `remote_id` IS NOT NULL
;
ALTER TABLE `files` DROP COLUMN `remote_id`;
EOF;

    public $sql_down = <<<EOF
ALTER TABLE `files` ADD COLUMN `remote_id` text;
UPDATE `files` f
SET `remote_id` = (
    SELECT
        `remote_id`
    FROM
        `remote_files` r
    WHERE
        r.`id_file` = f.`id` AND
        r.`is_original` = 1
    ORDER BY
        r.`id` DESC
    LIMIT 1
);
DELETE FROM `remote_files` WHERE `is_original` = 1;
ALTER TABLE `remote_files` DROP COLUMN `is_original`;
EOF;
}
