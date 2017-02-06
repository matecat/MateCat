<?php

use Phinx\Migration\AbstractMigration;

class UsersMigration extends AbstractMigration {
    public function up() {
        $this->execute( " LOCK TABLES `organizations` WRITE, `organizations_users` WRITE, `users` READ " );
        $this->execute( " BEGIN " );
        $this->execute( " INSERT INTO organizations ( name, created_by, type ) SELECT 'Personal', uid, 'personal' FROM users " );
        $this->execute( " INSERT INTO organizations_users ( id_organization, uid, is_admin ) SELECT id, created_by, 1 FROM organizations " );
        $this->execute( " COMMIT " );
        $this->execute( " UNLOCK TABLES " );
    }

    public function down(){

        $this->execute( " TRUNCATE TABLE organizations " );
        $this->execute( " TRUNCATE TABLE organizations_users " );

    }

}
