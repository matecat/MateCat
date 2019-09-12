<?php

class InserFeaturesInProjectMetadata extends AbstractMatecatMigration
{

    /**
     * Insert features in project metadata, reading from owner_features.
     *
     * Only operate on projects that were created after the addition of the owner feature to the user,
     * so that we avoid strange due to inconsistencies with old projects receiving features that were
     * not enabled when the project was created.
     *
     */
    public $sql_up = <<<EOF
    INSERT INTO project_metadata ( value, `key`, id_project ) 
    
           SELECT GROUP_CONCAT( f.feature_code ), 'features', projects.id 
           
           FROM projects 
           JOIN users               ON users.email = projects.id_customer 
           JOIN owner_features f    ON f.uid       = users.uid 
           
           WHERE 
                f.create_date > projects.create_date 
           
           GROUP BY projects.id ; 
EOF;

    public $sql_down = <<<EOF
    DELETE FROM project_metadata WHERE `key` = 'features'  ; 
EOF;

}
