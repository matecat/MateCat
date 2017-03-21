<?php


class AssigneeMigration extends AbstractMatecatMigration
{

    public $sql_up = [
            "LOCK TABLES `projects` WRITE, `teams` READ ",

            "UPDATE projects 
             JOIN teams on projects.id_team = teams.id
                SET id_assignee = teams.created_by
                WHERE projects.id_customer != 'translated_user' 
                AND projects.id_team IS NOT NULL
                AND id_assignee IS NULL
                AND teams.type = 'personal'; ",

            "UNLOCK TABLES ",
    ];

    public $sql_down = [
            "LOCK TABLES `projects` WRITE, `teams` READ ",

            "UPDATE projects 
             JOIN teams on projects.id_team = teams.id
                SET id_assignee = NULL
                WHERE projects.id_customer != 'translated_user' 
                AND projects.id_team IS NOT NULL
                AND teams.type = 'personal'; ",

            "UNLOCK TABLES ",
    ];

}
