<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:05
 */


route( '/api/v1/jobs/[:id_job]/[:password]/stats', 'GET',  'API\V1\StatsController', 'stats' );

$klein->with('/api/v1/projects/[:id_project]/[:password]', function() {
    route( '/creation_status',      'GET',  'API\V2\ProjectCreationStatusController',   'get' );
});
