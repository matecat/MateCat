<?php

use Phinx\Migration\AbstractMigration;

class AddNewAndOldPageOnVersions extends AbstractMigration
{
    public $sql_up = [
            "ALTER TABLE `segment_translation_versions` ADD COLUMN new_page ENUM ('translate', 'revise') DEFAULT NULL, algorithm=INPLACE, lock=NONE"
    ];

    public $sql_down = [
            "ALTER TABLE `segment_translation_versions` DROP COLUMN new_page, algorithm=INPLACE, lock=NONE"
    ];
}
