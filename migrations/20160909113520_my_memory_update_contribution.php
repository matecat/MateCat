<?php

class MyMemoryUpdateContribution extends AbstractMatecatMigration
{
    public $sql_up = <<<EOF
ALTER TABLE `engines` 
ADD COLUMN `update_relative_url` VARCHAR(100) NULL DEFAULT NULL AFTER `contribute_relative_url`;

UPDATE `engines` SET update_relative_url = 'update_segment.php' WHERE ID = 1;
EOF;

    public $sql_down = <<<EOF
ALTER TABLE `engines` 
DROP COLUMN `update_relative_url`;
EOF;

}
