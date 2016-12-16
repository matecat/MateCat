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

route( '/api/app/user',                         'GET', 'API\App\UserController', 'show' );
route( '/api/app/user/password',                'POST', 'API\App\UserController', 'updatePassword' );

route( '/api/app/user/login',                   'POST', 'API\App\LoginController', 'login' );
route( '/api/app/user/logout',                  'POST', 'API\App\LoginController', 'logout' );

route( '/api/app/user',                         'POST', 'API\App\SignupController', 'create' );
route( '/api/app/user/metadata',                'POST',  'API\App\UserMetadataController', 'update' );

route( '/api/app/user/resend_email_confirm',    'POST', 'API\App\SignupController', 'resendEmailConfirm' );
route( '/api/app/user/forgot_password',         'POST', 'API\App\SignupController', 'forgotPassword' );
route( '/api/app/user/password_reset/[:token]', 'GET', 'API\App\SignupController',  'authForPasswordReset' );
route( '/api/app/user/confirm/[:token]',        'GET', 'API\App\SignupController', 'confirm' );
route( '/api/app/user/redeem_project',          'POST', 'API\App\SignupController', 'redeemProject' );

route(
    '/api/app/connected_services/[:id_service]/verify', 'GET',
    'ConnectedServices\ConnectedServicesController', 'verify'
);

route(
    '/api/app/connected_services/[:id_service]', 'POST',
    'ConnectedServices\ConnectedServicesController', 'update'
);
