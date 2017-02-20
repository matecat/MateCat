<?php

class InsertIntoProjectsOrganizationIds extends AbstractMatecatMigration
{

    public $sql_up = array(

            "LOCK TABLES `projects` WRITE, `users` READ, `organizations` READ ",

            "UPDATE projects
                LEFT JOIN users ON users.email = projects.id_customer
                LEFT JOIN organizations ON organizations.created_by = users.uid AND organizations.type = 'personal'
              SET projects.id_organization = organizations.id ",

            "UNLOCK TABLES ",

    );

    public $sql_down = array(
            " UPDATE projects SET id_organization = NULL WHERE 1 "
    );

}
