<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:17
 */

$klein->respond( '/utils/pee', function () {

    $reflect  = new ReflectionClass( 'peeViewController' );
    $instance = $reflect->newInstanceArgs( func_get_args() );

    try {
        $instance->doAction();
        $instance->finalize();
    } catch ( Exception $e ) {
        $controllerInstance = new CustomPage();
        $controllerInstance->setTemplate( "404.html" );
        $controllerInstance->setCode( 404 );
        $controllerInstance->doAction();
        die(); // do not complete klein response, set 404 header in render404 instead of 200
    }

} );

route( '/api/app/user',                                                             'GET',  'API\App\UserController', 'show' );
route( '/api/app/user/password',                                                    'POST', 'API\App\UserController', 'updatePassword' );

route( '/api/app/user/login',                                                       'POST', 'API\App\LoginController', 'login' );
route( '/api/app/user/logout',                                                      'POST', 'API\App\LoginController', 'logout' );

route( '/api/app/user',                                                             'POST', 'API\App\SignupController', 'create' );
route( '/api/app/user/metadata',                                                    'POST', 'API\App\UserMetadataController', 'update' );

route( '/api/app/user/resend_email_confirm',                                        'POST', 'API\App\SignupController', 'resendEmailConfirm' );
route( '/api/app/user/forgot_password',                                             'POST', 'API\App\SignupController', 'forgotPassword' );
route( '/api/app/user/password_reset/[:token]',                                     'GET',  'API\App\SignupController', 'authForPasswordReset' );
route( '/api/app/user/confirm/[:token]',                                            'GET',  'API\App\SignupController', 'confirm' );
route( '/api/app/user/redeem_project',                                              'POST', 'API\App\SignupController', 'redeemProject' );

route( '/api/app/connected_services/[:id_service]/verify',                          'GET',  'ConnectedServices\ConnectedServicesController', 'verify' );
route( '/api/app/connected_services/[:id_service]',                                 'POST', 'ConnectedServices\ConnectedServicesController', 'update' );

route( '/api/app/teams/members/invite/[:jwt]',                                      'GET',  '\API\App\TeamsInvitationsController', 'collectBackInvitation' ) ;

route( '/api/app/outsource/confirm/[i:id_job]/[:password]',                         'POST', '\API\App\OutsourceConfirmationController', 'confirm' ) ;

$klein->with('/api/app/dqf', function(\Klein\Klein $klein) {
    route('/login/check',                                     'GET',  'Features\Dqf\Controller\API\LoginCheckController', 'check');
    route('/login',                                           'GET',  'Features\Dqf\Controller\API\LoginController', 'login');
    route('/jobs/[:id_job]/[:password]/assign',               'POST', 'Features\Dqf\Controller\API\GenericController', 'assignProject' );
    route('/jobs/[:id_job]/[:password]/[:page]/assignment/revoke', 'DELETE', 'Features\Dqf\Controller\API\GenericController', 'revokeAssignment' );
    route('/projects/[:id_project]/[:password]/assignments',  'GET', 'Features\Dqf\Controller\API\AssignmentsController', 'listAssignments' );
    route('/user/metadata',                                   'DELETE', 'Features\Dqf\Controller\API\GenericController', 'clearCredentials' );
});


route( '/api/app/utils/pee/graph',                                                  'POST', '\API\App\PeeData', 'getPeePlots' ) ;
route( '/api/app/utils/pee/table',                                                  'POST', '\API\App\PeeData', 'getPeeTableData' ) ;
route( '/api/app/jobs/[i:id_job]/[:password]/completion-events/[:id_event]',        'DELETE', 'Features\ProjectCompletion\Controller\CompletionEventController', 'delete' ) ;
