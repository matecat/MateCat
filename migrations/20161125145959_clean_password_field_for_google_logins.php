<?php

/**
 * Class CleanPasswordFieldForGoogleLogins
 *
 * This migration is meant to remove the password field from users to transition
 * into the email signin feature.
 *
 * Empty password is used to determine whether or not to show the reset password button
 * in preferences.
 *
 * Google logins no longer set salt and pass fields.
 *
 */

class CleanPasswordFieldForGoogleLogins extends AbstractMatecatMigration
{

    public $sql_up = array(
        " ALTER TABLE users MODIFY COLUMN pass VARCHAR(50) DEFAULT NULL ",
        " ALTER TABLE users MODIFY COLUMN salt VARCHAR(50) DEFAULT NULL ",
        " UPDATE users set pass = NULL, salt = NULL WHERE oauth_access_token IS NULL "
    );

    public $sql_down = array(
        " ALTER TABLE users MODIFY COLUMN pass VARCHAR(50) NOT NULL ",
        " ALTER TABLE users MODIFY COLUMN salt VARCHAR(50) NOT NULL "
    );


}
