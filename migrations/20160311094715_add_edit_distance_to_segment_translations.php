<?php

class AddEditDistanceToSegmentTranslations extends AbstractMatecatMigration
{
    public $sql_up = "ALTER TABLE `segment_translations` ADD COLUMN `edit_distance` int(11)";
    public $sql_down = "ALTER TABLE `segment_translations` DROP COLUMN `edit_distance`; ";
}
