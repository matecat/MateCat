<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:05
 */
global $klein;

route( '/api/v1/jobs/[:id_job]/[:password]/stats', 'GET', [ 'Controller\API\V2\StatsController', 'stats' ] );

$klein->with( '/api/v1/projects/[:id_project]/[:password]', function () {
    route( '/creation_status', 'GET', [ 'Controller\API\V2\ProjectCreationStatusController', 'get' ] );
} );

route( '/api/v1/new', 'POST', [ 'Controller\API\V1\NewController', 'create' ] );