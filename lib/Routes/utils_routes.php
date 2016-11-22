<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:17
 */

$klein->respond('GET', '/utils/pee', function() {
    $reflect  = new ReflectionClass('peeViewController');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->doAction();
    $instance->finalize();
});

route( '/api/app/user', 'GET', 'API\App\UserInfoController', 'show' );

route(
    '/api/app/connected_services/[:id_service]/verify', 'GET',
    'ConnectedServices\ConnectedServicesController', 'verify'
);
