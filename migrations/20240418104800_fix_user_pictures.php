<?php

// IMPORTANT: launch the migration shortly before the end of the deployment to avoid key duplication

use migrations\AbstractMatecatMigration;

class FixUserPictures extends AbstractMatecatMigration {

    public $sql_up = [
        "UPDATE user_metadata SET `key` = 'google_picture' where `key` = 'gplus_picture';",

    ];

    public $sql_down = [
        "UPDATE user_metadata SET `key` = 'gplus_picture' where `key` = 'google_picture';"
    ];
}

