<?php

use Phinx\Migration\AbstractMigration;

class AddStandardWcToJobTable extends AbstractMatecatMigration
{
    public $sql_up = [ 'ALTER TABLE `jobs` ADD COLUMN `standard_analysis_wc` DOUBLE(20,2) NULL DEFAULT 0.00 AFTER `total_raw_wc`' ];

    public $sql_down = ['ALTER TABLE `jobs` DROP COLUMN `standard_analysis_wc`' ] ;
}
