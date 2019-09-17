<?php

class UpdateEmailConfirmedAt extends AbstractMatecatMigration
{

    public $sql_up = [
        " UPDATE `users` SET email_confirmed_at = create_date "
    ];

    public $sql_down = [
        " UPDATE `users` SET email_confirmed_at = NULL "
    ];
}
