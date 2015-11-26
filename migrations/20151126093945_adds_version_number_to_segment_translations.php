<?php

use Phinx\Migration\AbstractMigration;

class AddsVersionNumberToSegmentTranslations extends AbstractMigration {
    public $sql_up = <<<EOF
ALTER TABLE `segment_translations` ADD COLUMN `version_number` int(11) DEFAULT 0 ;
EOF;

    public $sql_down = "ALTER TABLE `segment_translations` DROP COLUMN `version_number` ; ";


    public function up() {
        $this->execute($this->sql_up);
    }

    public function down() {
        $this->execute($this->sql_down);
    }
}
