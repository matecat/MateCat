<?php

class AddGoogleAccessTokenToUsers extends AbstractMatecatMigration {
    public $sql_up = "
ALTER TABLE `users` ADD COLUMN `oauth_access_token` TEXT ;
ALTER TABLE `files` ADD COLUMN `remote_id` TEXT ;

";
    public $sql_down = "
ALTER TABLE `users` DROP COLUMN `oauth_access_token` ;
ALTER TABLE `files` DROP COLUMN `remote_id` ;

";

}
