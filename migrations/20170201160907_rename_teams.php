<?php

use Phinx\Migration\AbstractMigration;

class RenameTeams extends AbstractMigration
{

    public function up() {

        $this->execute(" ALTER TABLE teams RENAME TO organizations" ) ;
        $this->execute(" ALTER TABLE teams_users CHANGE id_team id_organization int(11)" ) ;

        $this->execute(" ALTER TABLE teams_users RENAME INDEX id_team_uid TO id_organization_uid " ) ;
        $this->execute(" ALTER TABLE teams_users RENAME TO organizations_users " ) ;

        $this->execute(" ALTER TABLE owner_features CHANGE id_team id_organization int(11) " ) ;
        $this->execute(" ALTER TABLE owner_features RENAME INDEX id_team_feature TO id_organization_feature " ) ;

        $this->execute(" ALTER TABLE projects CHANGE id_team id_organization int(11)" );

    }

    public function down() {

        $this->execute(" ALTER TABLE organizations RENAME TO teams" ) ;
        $this->execute(" ALTER TABLE organizations_users CHANGE id_organization id_team int(11)" ) ;

        $this->execute(" ALTER TABLE organizations_users RENAME INDEX id_organization_uid TO id_team_uid " ) ;
        $this->execute(" ALTER TABLE organizations_users RENAME TO teams_users " ) ;

        $this->execute(" ALTER TABLE owner_features CHANGE id_organization id_team int(11) " ) ;
        $this->execute(" ALTER TABLE owner_features RENAME INDEX id_organization_feature TO id_team_feature " ) ;

        $this->execute(" ALTER TABLE projects CHANGE id_organization id_team int(11)" );

    }
}


