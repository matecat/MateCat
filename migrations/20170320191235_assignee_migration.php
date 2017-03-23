<?php


class AssigneeMigration extends AbstractMatecatMigration
{

    public $sql_up = [
            "LOCK TABLES `projects` WRITE, `users` READ ",

            "UPDATE projects 
             JOIN users ON users.email = projects.id_customer
                SET id_assignee = users.uid
                WHERE projects.id_customer != 'translated_user' 
                AND id_assignee IS NULL;",

            "UNLOCK TABLES ",
    ];

    public $sql_down = [
            "UPDATE projects 
                SET id_assignee = NULL;
             ",
    ];

}
