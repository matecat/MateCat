<?php

use Phinx\Migration\AbstractMigration;

class RenameTeams extends AbstractMatecatMigration
{

    public $sql_up = array(
        " ALTER TABLE teams RENAME TO organizations",
        " ALTER TABLE teams_users CHANGE id_team id_organization int(11)"  ,
        " ALTER TABLE teams_users RENAME INDEX id_team_uid TO id_organization_uid ",
        " ALTER TABLE teams_users RENAME TO organizations_users " ,
        " ALTER TABLE owner_features CHANGE id_team id_organization int(11) ",
        " ALTER TABLE owner_features RENAME INDEX id_team_feature TO id_organization_feature ",
        " ALTER TABLE projects CHANGE id_team id_organization int(11)" ,
    );

    public $sql_down = array(
        " ALTER TABLE organizations RENAME TO teams" ,
        " ALTER TABLE organizations_users CHANGE id_organization id_team int(11)" ,
        " ALTER TABLE organizations_users RENAME INDEX id_organization_uid TO id_team_uid ",
        " ALTER TABLE organizations_users RENAME TO teams_users " ,
        " ALTER TABLE owner_features CHANGE id_organization id_team int(11) ",
        " ALTER TABLE owner_features RENAME INDEX id_organization_feature TO id_team_feature ",
        " ALTER TABLE projects CHANGE id_organization id_team int(11)"
    );

}


