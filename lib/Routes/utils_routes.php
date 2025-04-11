<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:17
 */

global $klein;

route( '/api/app/teams/[i:id_team]/[:team_name]/members/public', 'GET', [ '\API\App\TeamPublicMembersController', 'publicList' ] );

// Authentication
$klein->with( '/api/app/user', function () {

    route( '', 'GET', [ 'API\App\Authentication\UserController', 'show' ] );
    route( '/password/change', 'POST', [ 'API\App\Authentication\UserController', 'changePasswordAsLoggedUser' ] );

    route( '/login', 'POST', [ 'API\App\Authentication\LoginController', 'login' ] );
    route( '/logout', 'POST', [ 'API\App\Authentication\LoginController', 'directLogout' ] );
    route( '/login/token', 'GET', [ 'API\App\Authentication\LoginController', 'token' ] );
    route( '/login/socket', 'GET', [ 'API\App\Authentication\LoginController', 'socketToken' ] );

    route( '/metadata', 'POST', [ 'API\App\UserMetadataController', 'update' ] );

    route( '', 'POST', [ 'API\App\Authentication\SignupController', 'create' ] );
    route( '/confirm/[:token]', 'GET', [ 'API\App\Authentication\SignupController', 'confirm' ] );
    route( '/resend_email_confirm', 'POST', [ 'API\App\Authentication\SignupController', 'resendConfirmationEmail' ] );

    route( '/forgot_password', 'POST', [ 'API\App\Authentication\ForgotPasswordController', 'forgotPassword' ] );
    route( '/password_reset/[:token]', 'GET', [ 'API\App\Authentication\ForgotPasswordController', 'authForPasswordReset' ] );
    route( '/password', 'POST', [ 'API\App\Authentication\ForgotPasswordController', 'setNewPassword' ] );

    route( '/redeem_project', 'POST', [ 'API\App\Authentication\UserController', 'redeemProject' ] );

} );

route( '/api/app/connected_services/[:id_service]/verify', 'GET', [ 'ConnectedServices\ConnectedServicesController', 'verify' ] );
route( '/api/app/connected_services/[:id_service]', 'POST', [ 'ConnectedServices\ConnectedServicesController', 'update' ] );

route( '/api/app/teams/members/invite/[:jwt]', 'GET', [ '\API\App\TeamsInvitationsController', 'collectBackInvitation' ] );

route( '/api/app/outsource/confirm/[i:id_job]/[:password]', 'POST', [ '\API\App\OutsourceConfirmationController', 'confirm' ] );

route( '/api/app/jobs/[i:id_job]/[:password]/completion-events/[:id_event]', 'DELETE', [ 'Features\ProjectCompletion\Controller\CompletionEventController', 'delete' ] );

//Health check
route( '/api/app/heartbeat/ping', 'GET', [ '\API\App\HeartBeat', 'ping' ] );

$klein->with( '/api/app/jobs/[:id_job]/[:password]', function () {
    route( '', 'GET', [ '\API\V3\ChunkController', 'show' ] );
    route( '/quality-report', 'GET', [ '\Features\ReviewExtended\Controller\API\QualityReportController', 'show' ] ); // alias of /api/v2/jobs/[:id_job]/[:password]/quality-report
    route( '/quality-report/segments', 'GET', [ 'Features\ReviewExtended\Controller\API\QualityReportController', 'segments_for_ui' ] ); // alias of /api/v2/jobs/[:id_job]/[:password]/quality-report/segments
} );

route( '/api/app/jobs/[:id_job]/[:password]/stats', 'GET', [ 'API\App\StatsController', 'stats' ] );
route( '/api/app/jobs/[:id_job]/[:password]/segments', 'POST', [ 'API\App\FilesController', 'segments' ] );

$klein->with( '/api/app/api-key', function () {
    route( '/create', 'POST', [ '\API\App\ApiKeyController', 'create' ] );
    route( '/show', 'GET', [ '\API\App\ApiKeyController', 'show' ] );
    route( '/delete', 'DELETE', [ '\API\App\ApiKeyController', 'delete' ] );
} );

route( '/api/app/projects/[:id_project]/[:password]/segment-analysis', 'GET', [ 'API\V3\SegmentAnalysisController', 'project' ] ); // to be deleted from here
route( '/api/app/jobs/[:id_job]/[:password]/segment-analysis', 'GET', [ 'API\V3\SegmentAnalysisController', 'job' ] );     // to be deleted from here

route( '/api/app/projects/[:id_project]/[:password]/quality-framework', 'GET', [ 'API\App\QualityFrameworkController', 'project' ] );
route( '/api/app/jobs/[:id_job]/[:password]/quality-framework', 'GET', [ 'API\App\QualityFrameworkController', 'job' ] );

route( '/api/app/change-password', 'POST', [ 'API\V2\ChangePasswordController', 'changePassword' ] );
route( '/api/app/projects/[:id_project]/[:password]/change-name', 'POST', [ 'API\V2\ChangeProjectNameController', 'changeName' ] );

// TM Keys
$klein->with( '/api/app/tm-keys', function () {
    route( '/[:id_job]/[:password]', 'GET', [ '\API\App\TmKeyManagementController', 'getByJob' ] );
    route( '/engines/info/[:key]', 'GET', [ '\API\App\TmKeyManagementController', 'getByUserAndKey' ] );
} );

// Glossary
$klein->with( '/api/app/glossary', function () {
    route( '/_check', 'POST', [ '\API\App\GlossaryController', 'check' ] );
    route( '/_delete', 'POST', [ '\API\App\GlossaryController', 'delete' ] );
    route( '/_domains', 'POST', [ '\API\App\GlossaryController', 'domains' ] );
    route( '/_get', 'POST', [ '\API\App\GlossaryController', 'get' ] );
    route( '/_keys', 'POST', [ '\API\App\GlossaryController', 'keys' ] );
    route( '/_search', 'POST', [ '\API\App\GlossaryController', 'search' ] );
    route( '/_set', 'POST', [ '\API\App\GlossaryController', 'set' ] );
    route( '/_update', 'POST', [ '\API\App\GlossaryController', 'update' ] );
} );

// AI Assistant
route( '/api/app/ai-assistant', 'POST', [ 'API\App\AIAssistantController', 'index' ] );

$klein->with( '/api/app/languages', function () {
    route( '', 'GET', [ '\API\App\SupportedLanguagesController', 'index' ] );
} );

$klein->with( '/api/app/files', function () {
    route( '', 'GET', [ '\API\App\SupportedFilesController', 'index' ] );
} );

//PAYABLE RATES
$klein->with( '/api/app/payable_rate', function () {
    route( '/default', 'GET', [ '\API\V3\PayableRateController', 'default' ] );
} );

//QA MODELS
$klein->with( '/api/app/qa_model_template', function () {
    route( '/default', 'GET', [ '\API\V3\QAModelTemplateController', 'default' ] );
} );

// PROJECT TEMPLATE
$klein->with( '/api/app/project-template', function () {
    route( '/default', 'GET', [ '\API\V3\ProjectTemplateController', 'default' ] );
} );

// FILTERS CONFIG
$klein->with( '/api/app/filters-config-template', function () {
    route( '/default', 'GET', [ '\API\V3\FiltersConfigTemplateController', 'default' ] );
} );

// MISC (OLD AJAX ROUTES)
route( '/api/app/fetch-change-rates', 'POST', [ 'API\App\FetchChangeRatesController', 'fetch' ] );
route( '/api/app/outsource-to', 'POST', [ 'API\App\OutsourceToController', 'outsource' ] );
route( '/api/app/get-volume-analysis', 'POST', [ 'API\App\GetVolumeAnalysisController', 'analysis' ] );
route( '/api/app/get-projects', 'POST', [ 'API\App\GetProjectsController', 'fetch' ] );
route( '/api/app/delete-contribution', 'POST', [ 'API\App\DeleteContributionController', 'delete' ] );
route( '/api/app/comment/resolve', 'POST', [ 'API\App\CommentController', 'resolve' ] );
route( '/api/app/comment/delete', 'POST', [ 'API\App\CommentController', 'delete' ] );
route( '/api/app/comment/create', 'POST', [ 'API\App\CommentController', 'create' ] );
route( '/api/app/comment/get-range', 'POST', [ 'API\App\CommentController', 'getRange' ] );
route( '/api/app/copy-all-source-to-target', 'POST', [ 'API\App\CopyAllSourceToTargetController', 'copy' ] );
route( '/api/app/get-global-warning', 'POST', [ 'API\App\GetWarningController', 'global' ] );
route( '/api/app/get-local-warning', 'POST', [ 'API\App\GetWarningController', 'local' ] );
route( '/api/app/split-job-apply', 'POST', [ 'API\App\SplitJobController', 'apply' ] );
route( '/api/app/split-job-check', 'POST', [ 'API\App\SplitJobController', 'check' ] );
route( '/api/app/split-job-merge', 'POST', [ 'API\App\SplitJobController', 'merge' ] );
route( '/api/app/user-keys-delete', 'POST', [ 'API\App\UserKeysController', 'delete' ] );
route( '/api/app/user-keys-update', 'POST', [ 'API\App\UserKeysController', 'update' ] );
route( '/api/app/user-keys-new-key', 'POST', [ 'API\App\UserKeysController', 'newKey' ] );
route( '/api/app/user-keys-info', 'POST', [ 'API\App\UserKeysController', 'info' ] );
route( '/api/app/user-keys-share', 'POST', [ 'API\App\UserKeysController', 'share' ] );
route( '/api/app/create-random-user', 'POST', [ 'API\App\CreateRandUserController', 'create' ] );
route( '/api/app/get-tag-projection', 'POST', [ 'API\App\GetTagProjectionController', 'call' ] );
route( '/api/app/set-current-segment', 'POST', [ 'API\App\SetCurrentSegmentController', 'set' ] );
route( '/api/app/get-segments', 'POST', [ 'API\App\GetSegmentsController', 'segments' ] );
route( '/api/app/ping', 'POST', [ 'API\App\AjaxUtilsController', 'ping' ] );
route( '/api/app/check-tm-key', 'POST', [ 'API\App\AjaxUtilsController', 'checkTMKey' ] );
route( '/api/app/clear-not-completed-uploads', 'POST', [ 'API\App\AjaxUtilsController', 'clearNotCompletedUploads' ] );
route( '/api/app/get-translation-mismatches', 'POST', [ 'API\App\GetTranslationMismatchesController', 'get' ] );
route( '/api/app/add-engine', 'POST', [ 'API\App\EngineController', 'add' ] );
route( '/api/app/disable-engine', 'POST', [ 'API\App\EngineController', 'disable' ] );
route( '/api/app/get-contribution', 'POST', [ 'API\App\GetContributionController', 'get' ] );
route( '/api/app/search', 'POST', [ 'API\App\GetSearchController', 'search' ] );
route( '/api/app/replace-all', 'POST', [ 'API\App\GetSearchController', 'replaceAll' ] );
route( '/api/app/redo-replace-all', 'POST', [ 'API\App\GetSearchController', 'redoReplaceAll' ] );
route( '/api/app/undo-replace-all', 'POST', [ 'API\App\GetSearchController', 'undoReplaceAll' ] );
route( '/api/app/update-job-keys', 'POST', [ 'API\App\UpdateJobKeysController', 'update' ] );
route( '/api/app/set-translation', 'POST', [ 'API\App\SetTranslationController', 'translate' ] );
route( '/api/app/split-segment', 'POST', ['API\App\SplitSegmentController', 'split' ] );
route( '/api/app/new-tmx', 'POST', ['API\App\LoadTMXController', 'newTM' ] );
route( '/api/app/upload-tmx-status', 'POST', ['API\App\LoadTMXController', 'uploadStatus' ] );
route( '/api/app/change-job-status', 'POST', ['API\App\ChangeJobsStatusController', 'changeStatus' ] );
route( '/api/app/download-tmx', 'POST', ['API\App\DownloadTMXController', 'download' ] );
route( '/api/app/new-project', 'POST', ['API\App\CreateProjectController', 'create' ] );
route( '/api/app/convert-file', 'POST', ['API\App\ConvertFileController', 'handle' ] );
route( '/api/app/set-chunk-completed', 'POST', ['API\App\SetChunkCompletedController', 'complete' ] );
route( '/api/app/download-analysis-report', 'POST', ['API\App\DownloadAnalysisReportController', 'download' ] );

// Metadata
$klein->with( '/api/app/jobs/[:id_job]/[:password]/metadata', function () {
    route( '', 'GET', [ '\API\App\JobMetadataController', 'get' ] );
    route( '', 'POST', [ '\API\App\JobMetadataController', 'save' ] );
    route( '/[:key]', 'DELETE', [ '\API\App\JobMetadataController', 'delete' ] );
} );
