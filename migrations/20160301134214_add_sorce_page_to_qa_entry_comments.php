<?php

class AddSorcePageToQaEntryComments extends AbstractMatecatMigration
{
   public $sql_up = "ALTER TABLE `qa_entry_comments` ADD COLUMN `source_page` tinyint(4)";
   public $sql_down = "ALTER TABLE `qa_entry_comments` DROP COLUMN `source_page`; ";
}
