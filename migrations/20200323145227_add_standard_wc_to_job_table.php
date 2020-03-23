<?php

use Phinx\Migration\AbstractMigration;

class AddStandardWcToJobTable extends AbstractMigration  {

    public $sql_up = <<<EOF
      ALTER TABLE `jobs` 
ADD COLUMN `standard_analysis_wc` DOUBLE(20,2) NULL DEFAULT 0.00 AFTER `total_raw_wc`;
EOF;

    public $sql_down = <<<EOF
    ALTER TABLE `jobs` 
    DROP COLUMN `standard_analysis_wc`;
EOF;
}
