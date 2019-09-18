<?php

class UpdateUsers extends AbstractMatecatMigration
{

    public $sql_up = array(
        "ALTER TABLE users DROP COLUMN api_key ;  " ,

        " ALTER TABLE users ADD COLUMN email_confirmed_at timestamp,
            ADD COLUMN new_pass varchar(50),
            ADD COLUMN confirmation_token varchar(50),
            ADD COLUMN confirmation_token_created_at timestamp
            ; ",

        "CREATE UNIQUE INDEX `confirmation_token` ON users ( `confirmation_token` ) USING BTREE; "

    );

    public $sql_down = array(
        " ALTER TABLE users ADD COLUMN api_key varchar(100); ",

        " ALTER TABLE users DROP COLUMN email_confirmed_at, 
            DROP COLUMN new_pass,
            DROP COLUMN confirmation_token, 
            DROP COLUMN confirmation_token_created_at 
            ; ",

    );

}
