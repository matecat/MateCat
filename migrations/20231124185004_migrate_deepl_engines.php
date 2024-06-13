<?php

class MigrateDeepLEngines extends AbstractMatecatMigration {

    public $sql_up = [ "
        UPDATE engines SET translate_relative_url = 'v1/translate' where id > 1 and name = 'DeepL';
    " ];

    public $sql_down = [ "
        UPDATE engines SET translate_relative_url = 'translate' where id > 1 and name = 'DeepL';
    "];
}