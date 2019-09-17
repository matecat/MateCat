<?php

class RenameTeams extends AbstractMatecatMigration
{

    public $sql_up = array(
        " ALTER TABLE teams RENAME TO organizations",
        " ALTER TABLE teams_users CHANGE id_team id_organization int(11)"  ,
        " ALTER TABLE teams_users DROP INDEX id_team_uid, ADD UNIQUE INDEX id_organization_uid (id_organization, uid) ",
        " ALTER TABLE teams_users RENAME TO organizations_users " ,
        " ALTER TABLE owner_features CHANGE id_team id_organization int(11) ",
        " ALTER TABLE owner_features DROP INDEX id_team_feature, ADD UNIQUE INDEX id_organization_feature(id_organization,feature_code) ",
        " ALTER TABLE projects CHANGE id_team id_organization int(11)" ,
    );

    public $sql_down = array(
        " ALTER TABLE organizations RENAME TO teams" ,
        " ALTER TABLE organizations_users CHANGE id_organization id_team int(11)" ,
        " ALTER TABLE organizations_users DROP INDEX id_organization_uid, ADD UNIQUE INDEX id_team_uid( id_team ) ",
        " ALTER TABLE organizations_users RENAME TO teams_users " ,
        " ALTER TABLE owner_features CHANGE id_organization id_team int(11) ",
        " ALTER TABLE owner_features DROP INDEX id_organization_feature, ADD UNIQUE INDEX id_team_feature (id_team,feature_code) ",
        " ALTER TABLE projects CHANGE id_organization id_team int(11)"
    );

}


