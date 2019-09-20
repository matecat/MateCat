<?php

class UsersMigration extends AbstractMatecatMigration  {

    public $sql_up = array(
        " LOCK TABLES `organizations` WRITE, `organizations_users` WRITE, `users` READ ",
        " BEGIN ",
        " INSERT IGNORE INTO organizations ( name, created_by, type ) SELECT 'Personal', uid, 'personal' FROM users ",
        " INSERT IGNORE INTO organizations_users ( id_organization, uid, is_admin ) SELECT id, created_by, 1 FROM organizations ",
        " COMMIT ",
        " UNLOCK TABLES ",
    );

    public $sql_down = array(
        " TRUNCATE TABLE organizations ",
        " TRUNCATE TABLE organizations_users "
    );

}
