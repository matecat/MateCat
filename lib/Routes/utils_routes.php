<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:17
 */

global $klein;

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

route( '/api/app/teams/[i:id_team]/members/public',                                 'GET',  '\API\App\TeamPublicMembersController', 'publicList' );

route( '/api/app/user',                                                             'GET',  'API\App\UserController', 'show' );
route( '/api/app/user/password/change',                                             'POST', 'API\App\UserController', 'changePasswordAsLoggedUser' );


route( '/api/app/user/login',                                                       'POST', 'API\App\LoginController', 'login' );
route( '/api/app/user/logout',                                                      'POST', 'API\App\LoginController', 'logout' );
route( '/api/app/user/login/token',                                                 'GET',  'API\App\LoginController', 'token' );

route( '/api/app/user',                                                             'POST', 'API\App\SignupController', 'create' );
route( '/api/app/user/metadata',                                                    'POST', 'API\App\UserMetadataController', 'update' );

route( '/api/app/user/confirm/[:token]',                                            'GET',  'API\App\SignupController', 'confirm' );
route( '/api/app/user/resend_email_confirm',                                        'POST', 'API\App\SignupController', 'resendEmailConfirm' );
route( '/api/app/user/forgot_password',                                             'POST', 'API\App\SignupController', 'forgotPassword' );
route( '/api/app/user/password_reset/[:token]',                                     'GET',  'API\App\SignupController', 'authForPasswordReset' );
route( '/api/app/user/password',                                                    'POST', 'API\App\SignupController', 'setNewPassword' );
route( '/api/app/user/redeem_project',                                              'POST', 'API\App\SignupController', 'redeemProject' );

route( '/api/app/connected_services/[:id_service]/verify',                          'GET',  'ConnectedServices\ConnectedServicesController', 'verify' );
route( '/api/app/connected_services/[:id_service]',                                 'POST', 'ConnectedServices\ConnectedServicesController', 'update' );

route( '/api/app/teams/members/invite/[:jwt]',                                      'GET',  '\API\App\TeamsInvitationsController', 'collectBackInvitation' ) ;

route( '/api/app/outsource/confirm/[i:id_job]/[:password]',                         'POST', '\API\App\OutsourceConfirmationController', 'confirm' ) ;

route( '/api/app/utils/pee/graph',                                                  'POST', '\API\App\PeeData', 'getPeePlots' ) ;
route( '/api/app/utils/pee/table',                                                  'POST', '\API\App\PeeData', 'getPeeTableData' ) ;
route( '/api/app/jobs/[i:id_job]/[:password]/completion-events/[:id_event]',        'DELETE', 'Features\ProjectCompletion\Controller\CompletionEventController', 'delete' ) ;

//Health check
route( '/api/app/heartbeat/ping',                                                   'GET', '\API\App\HeartBeat', 'ping' ) ;

$klein->with('/api/app/jobs/[:id_job]/[:password]', function() {
    route( '', 'GET', '\API\V3\ChunkController', 'show' );
    route( '/quality-report',           'GET', '\Features\SecondPassReview\Controller\API\QualityReportController', 'show' ); // alias of /api/v2/jobs/[:id_job]/[:password]/quality-report
    route( '/quality-report/segments',  'GET', 'Features\SecondPassReview\Controller\API\QualityReportController', 'segments_for_ui' ); // alias of /api/v2/jobs/[:id_job]/[:password]/quality-report/segments
});

route( '/api/app/jobs/[:id_job]/[:password]/stats',     'GET',  'API\App\StatsController', 'stats' );
route( '/api/app/jobs/[:id_job]/[:password]/segments',  'POST',  'API\App\FilesController', 'segments' );

$klein->with( '/api/app/api-key', function () {
    route( '/create',   'POST', '\API\App\ApiKeyController', 'create' );
    route( '/show',     'GET', '\API\App\ApiKeyController', 'show' );
    route( '/delete',   'DELETE', '\API\App\ApiKeyController', 'delete' );
} );

route( '/api/app/projects/[:id_project]/[:password]/segment-analysis',  'GET',  'API\V3\SegmentAnalysisController', 'project' ); // to be deleted from here
route( '/api/app/jobs/[:id_job]/[:password]/segment-analysis',          'GET',  'API\V3\SegmentAnalysisController', 'job' );     // to be deleted from here

route( '/api/app/projects/[:id_project]/[:password]/quality-framework', 'GET',  'API\App\QualityFrameworkController', 'project' );
route( '/api/app/jobs/[:id_job]/[:password]/quality-framework',         'GET',  'API\App\QualityFrameworkController', 'job' );

route( '/api/app/change-password',  'POST', 'API\V2\ChangePasswordController', 'changePassword' );

// TM Keys
$klein->with( '/api/app/tm-keys', function () {
    route( '/[:id_job]/[:password]', 'GET', '\API\App\TmKeyManagementController', 'getByJob' );
} );

// Glossary
$klein->with( '/api/app/glossary', function () {
    route( '/_check', 'POST', '\API\App\GlossaryController', 'check' );
    route( '/_delete', 'POST', '\API\App\GlossaryController', 'delete' );
    route( '/_domains', 'POST', '\API\App\GlossaryController', 'domains' );
    route( '/_get', 'POST', '\API\App\GlossaryController', 'get' );
    route( '/_keys', 'POST', '\API\App\GlossaryController', 'keys' );
    route( '/_search', 'POST', '\API\App\GlossaryController', 'search' );
    route( '/_set', 'POST', '\API\App\GlossaryController', 'set' );
    route( '/_update', 'POST', '\API\App\GlossaryController', 'update' );
} );

// AI Assistant
route( '/api/app/ai-assistant', 'POST',  'API\App\AIAssistantController', 'index' );

$klein->with('/api/app/languages', function() {
    route( '', 'GET', '\API\App\SupportedLanguagesController', 'index' );
});

$klein->with('/api/app/files', function() {
    route( '', 'GET', '\API\App\SupportedFilesController', 'index' );
});

