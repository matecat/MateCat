<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:17
 */

global $klein;

route( '/api/app/teams/[i:id_team]/[:team_name]/members/public', 'GET', [ '\Controller\API\App\TeamPublicMembersController', 'publicList' ] );

// Authentication
$klein->with( '/api/app/user', function () {

    route( '', 'GET', [ 'Controller\API\App\Authentication\UserController', 'show' ] );
    route( '/password/change', 'POST', [ 'Controller\API\App\Authentication\UserController', 'changePasswordAsLoggedUser' ] );

    route( '/login', 'POST', [ 'Controller\API\App\Authentication\LoginController', 'login' ] );
    route( '/logout', 'POST', [ 'Controller\API\App\Authentication\LoginController', 'directLogout' ] );
    route( '/login/token', 'GET', [ 'Controller\API\App\Authentication\LoginController', 'token' ] );
    route( '/login/socket', 'GET', [ 'Controller\API\App\Authentication\LoginController', 'socketToken' ] );

    route( '/metadata', 'POST', [ 'Controller\API\App\UserMetadataController', 'update' ] );

    route( '', 'POST', [ 'Controller\API\App\Authentication\SignupController', 'create' ] );
    route( '/confirm/[:token]', 'GET', [ 'Controller\API\App\Authentication\SignupController', 'confirm' ] );
    route( '/resend_email_confirm', 'POST', [ 'Controller\API\App\Authentication\SignupController', 'resendConfirmationEmail' ] );

    route( '/forgot_password', 'POST', [ 'Controller\API\App\Authentication\ForgotPasswordController', 'forgotPassword' ] );
    route( '/password_reset/[:token]', 'GET', [ 'Controller\API\App\Authentication\ForgotPasswordController', 'authForPasswordReset' ] );
    route( '/password', 'POST', [ 'Controller\API\App\Authentication\ForgotPasswordController', 'setNewPassword' ] );

    route( '/redeem_project', 'POST', [ 'Controller\API\App\Authentication\UserController', 'redeemProject' ] );

} );

route( '/api/app/connected_services/[:id_service]/verify', 'GET', [ 'Controller\API\App\ConnectedServicesController', 'verify' ] );
route( '/api/app/connected_services/[:id_service]', 'POST', [ 'Controller\API\App\ConnectedServicesController', 'update' ] );

route( '/api/app/teams/members/invite/[:jwt]', 'GET', [ '\Controller\API\App\TeamsInvitationsController', 'collectBackInvitation' ] );

route( '/api/app/outsource/confirm/[i:id_job]/[:password]', 'POST', [ '\Controller\API\App\OutsourceConfirmationController', 'confirm' ] );

route( '/api/app/jobs/[i:id_job]/[:password]/completion-events/[:id_event]', 'DELETE', [ 'Controller\API\App\CompletionEventController', 'delete' ] );

//Health check
route( '/api/app/heartbeat/ping', 'GET', [ '\Controller\API\App\HeartBeat', 'ping' ] );

$klein->with( '/api/app/jobs/[:id_job]/[:password]', function () {
    route( '', 'GET', [ '\Controller\API\V3\ChunkController', 'show' ] );
    route( '/quality-report', 'GET', [ '\Controller\API\App\QualityReportControllerAPI', 'show' ] ); // alias of /api/v2/jobs/[:id_job]/[:password]/quality-report
    route( '/quality-report/segments', 'GET', [ 'Controller\API\App\QualityReportControllerAPI', 'segments_for_ui' ] ); // alias of /api/v2/jobs/[:id_job]/[:password]/quality-report/segments
} );

route( '/api/app/jobs/[:id_job]/[:password]/stats', 'GET', [ 'Controller\API\V2\StatsController', 'stats' ] );
route( '/api/app/jobs/[:id_job]/[:password]/segments', 'POST', [ 'Controller\API\App\FilesController', 'segments' ] );

$klein->with( '/api/app/api-key', function () {
    route( '/create', 'POST', [ '\Controller\API\App\ApiKeyController', 'create' ] );
    route( '/show', 'GET', [ '\Controller\API\App\ApiKeyController', 'show' ] );
    route( '/delete', 'DELETE', [ '\Controller\API\App\ApiKeyController', 'delete' ] );
} );

route( '/api/app/projects/[:id_project]/[:password]/segment-analysis', 'GET', [ 'Controller\API\V3\SegmentAnalysisController', 'project' ] ); // to be deleted from here
route( '/api/app/jobs/[:id_job]/[:password]/segment-analysis', 'GET', [ 'Controller\API\V3\SegmentAnalysisController', 'job' ] );     // to be deleted from here

route( '/api/app/projects/[:id_project]/[:password]/quality-framework', 'GET', [ 'Controller\API\App\QualityFrameworkController', 'project' ] );
route( '/api/app/jobs/[:id_job]/[:password]/quality-framework', 'GET', [ 'Controller\API\App\QualityFrameworkController', 'job' ] );

route( '/api/app/change-password', 'POST', [ 'Controller\API\V2\ChangePasswordController', 'changePassword' ] );
route( '/api/app/projects/[:id_project]/[:password]/change-name', 'POST', [ 'Controller\API\V2\ChangeProjectNameController', 'changeName' ] );

// TM Keys
$klein->with( '/api/app/tm-keys', function () {
    route( '/[:id_job]/[:password]', 'GET', [ '\Controller\API\App\TmKeyManagementController', 'getByJob' ] );
    route( '/engines/info/[:key]', 'GET', [ '\Controller\API\App\TmKeyManagementController', 'getByUserAndKey' ] );
} );

// Glossary
$klein->with( '/api/app/glossary', function () {
    route( '/_check', 'POST', [ '\Controller\API\App\GlossaryController', 'check' ] );
    route( '/_delete', 'POST', [ '\Controller\API\App\GlossaryController', 'delete' ] );
    route( '/_domains', 'POST', [ '\Controller\API\App\GlossaryController', 'domains' ] );
    route( '/_get', 'POST', [ '\Controller\API\App\GlossaryController', 'get' ] );
    route( '/_keys', 'POST', [ '\Controller\API\App\GlossaryController', 'keys' ] );
    route( '/_search', 'POST', [ '\Controller\API\App\GlossaryController', 'search' ] );
    route( '/_set', 'POST', [ '\Controller\API\App\GlossaryController', 'set' ] );
    route( '/_status', 'POST', [ '\Controller\API\App\GlossaryController', 'status' ] );
    route( '/_update', 'POST', [ '\Controller\API\App\GlossaryController', 'update' ] );
} );

// Intento
$klein->with( '/api/app/intento', function () {
    route( '/routing/[:engineId]', 'GET', [ '\Controller\API\App\IntentoController', 'routingList' ] );
} );

// MyMemory
$klein->with( '/api/app/mymemory', function () {

    // Glossary entries
    route( '/glossary/_check', 'POST', [ '\Controller\API\App\GlossaryController', 'check' ] );
    route( '/glossary/_delete', 'POST', [ '\Controller\API\App\GlossaryController', 'delete' ] );
    route( '/glossary/_domains', 'POST', [ '\Controller\API\App\GlossaryController', 'domains' ] );
    route( '/glossary/_get', 'POST', [ '\Controller\API\App\GlossaryController', 'get' ] );
    route( '/glossary/_keys', 'POST', [ '\Controller\API\App\GlossaryController', 'keys' ] );
    route( '/glossary/_search', 'POST', [ '\Controller\API\App\GlossaryController', 'search' ] );
    route( '/glossary/_set', 'POST', [ '\Controller\API\App\GlossaryController', 'set' ] );
    route( '/glossary/_update', 'POST', [ '\Controller\API\App\GlossaryController', 'update' ] );

    # Glossary file import
    route( '/glossary/import/', 'POST', [ '\Controller\API\V2\GlossaryFilesController', 'import' ] );
    route( '/glossary/import/status/[:uuid]', 'GET', [ '\Controller\API\V2\GlossaryFilesController', 'importStatus' ] );

    # single entry (ex: glossary or memory)
    route( '/entry/status/[:uuid]', 'GET', [ '\Controller\API\App\MyMemoryEntryStatusController', 'status' ] );

    # TMX Files
    route( '/tmx/import/status/[:uuid]', 'GET', [ 'Controller\API\App\TMXFileController', 'importStatus' ] );
    route( '/tmx/import', 'POST', [ 'Controller\API\App\TMXFileController', 'import' ] );
} );

// AI Assistant
route( '/api/app/ai-assistant', 'POST', [ 'Controller\API\App\AIAssistantController', 'index' ] );

$klein->with( '/api/app/languages', function () {
    route( '', 'GET', [ '\Controller\API\App\SupportedLanguagesController', 'index' ] );
} );

$klein->with( '/api/app/files', function () {
    route( '', 'GET', [ '\Controller\API\V2\SupportedFilesController', 'index' ] );
} );

//PAYABLE RATES
$klein->with( '/api/app/payable_rate', function () {
    route( '/default', 'GET', [ '\Controller\API\V3\PayableRateController', 'default' ] );
} );

//QA MODELS
$klein->with( '/api/app/qa_model_template', function () {
    route( '/default', 'GET', [ '\Controller\API\V3\QAModelTemplateController', 'default' ] );
} );

// PROJECT TEMPLATE
$klein->with( '/api/app/project-template', function () {
    route( '/default', 'GET', [ '\Controller\API\V3\ProjectTemplateController', 'default' ] );
} );

// FILTERS CONFIG
$klein->with( '/api/app/filters-config-template', function () {
    route( '/default', 'GET', [ '\Controller\API\V3\FiltersConfigTemplateController', 'default' ] );
} );

route( '/api/app/projects/[:id_project]/token/[:project_access_token]', 'GET', [ 'Controller\API\V2\ProjectsController', 'get' ] );

// MISC (OLD AJAX ROUTES)
route( '/api/app/xliff-to-target/convert', 'POST', [ 'Controller\API\App\XliffToTargetConverterController', 'convert' ] );
route( '/api/app/fetch-change-rates', 'POST', [ 'Controller\API\App\FetchChangeRatesController', 'fetch' ] );
route( '/api/app/outsource-to', 'POST', [ 'Controller\API\App\OutsourceToController', 'outsource' ] );
route( '/api/app/get-volume-analysis', 'POST', [ 'Controller\API\App\GetVolumeAnalysisController', 'analysis' ] );
route( '/api/app/get-projects', 'POST', [ 'Controller\API\App\GetProjectsController', 'fetch' ] );
route( '/api/app/delete-contribution', 'POST', [ 'Controller\API\App\DeleteContributionController', 'delete' ] );
route( '/api/app/comment/resolve', 'POST', [ 'Controller\API\App\CommentController', 'resolve' ] );
route( '/api/app/comment/delete', 'POST', [ 'Controller\API\App\CommentController', 'delete' ] );
route( '/api/app/comment/create', 'POST', [ 'Controller\API\App\CommentController', 'create' ] );
route( '/api/app/comment/get-range', 'POST', [ 'Controller\API\App\CommentController', 'getRange' ] );
route( '/api/app/copy-all-source-to-target', 'POST', [ 'Controller\API\App\CopyAllSourceToTargetController', 'copy' ] );
route( '/api/app/get-global-warning', 'POST', [ 'Controller\API\App\GetWarningController', 'global' ] );
route( '/api/app/get-local-warning', 'POST', [ 'Controller\API\App\GetWarningController', 'local' ] );
route( '/api/app/split-job-apply', 'POST', [ 'Controller\API\V2\SplitJobController', 'apply' ] ); // Same API as public V2
route( '/api/app/split-job-check', 'POST', [ 'Controller\API\V2\SplitJobController', 'check' ] ); // Same API as public V2
route( '/api/app/split-job-merge', 'POST', [ 'Controller\API\V2\SplitJobController', 'merge' ] ); // Same API as public V2
route( '/api/app/user-keys-delete', 'POST', [ 'Controller\API\App\UserKeysController', 'delete' ] );
route( '/api/app/user-keys-update', 'POST', [ 'Controller\API\App\UserKeysController', 'update' ] );
route( '/api/app/user-keys-new-key', 'POST', [ 'Controller\API\App\UserKeysController', 'newKey' ] );
route( '/api/app/user-keys-info', 'POST', [ 'Controller\API\App\UserKeysController', 'info' ] );
route( '/api/app/user-keys-share', 'POST', [ 'Controller\API\App\UserKeysController', 'share' ] );
route( '/api/app/create-random-user', 'POST', [ 'Controller\API\App\CreateRandUserController', 'create' ] );
route( '/api/app/get-tag-projection', 'POST', [ 'Controller\API\App\GetTagProjectionController', 'call' ] );
route( '/api/app/set-current-segment', 'POST', [ 'Controller\API\App\SetCurrentSegmentController', 'set' ] );
route( '/api/app/get-segments', 'POST', [ 'Controller\API\App\GetSegmentsController', 'segments' ] );
route( '/api/app/ping', 'POST', [ 'Controller\API\App\AjaxUtilsController', 'ping' ] );
route( '/api/app/check-tm-key', 'POST', [ 'Controller\API\App\AjaxUtilsController', 'checkTMKey' ] );
route( '/api/app/clear-not-completed-uploads', 'POST', [ 'Controller\API\App\AjaxUtilsController', 'clearNotCompletedUploads' ] );
route( '/api/app/get-translation-mismatches', 'POST', [ 'Controller\API\App\GetTranslationMismatchesController', 'get' ] );
route( '/api/app/add-engine', 'POST', [ 'Controller\API\App\EngineController', 'add' ] );
route( '/api/app/disable-engine', 'POST', [ 'Controller\API\App\EngineController', 'disable' ] );
route( '/api/app/get-contribution', 'POST', [ 'Controller\API\App\GetContributionController', 'get' ] );
route( '/api/app/search', 'POST', [ 'Controller\API\App\GetSearchController', 'search' ] );
route( '/api/app/replace-all', 'POST', [ 'Controller\API\App\GetSearchController', 'replaceAll' ] );
route( '/api/app/redo-replace-all', 'POST', [ 'Controller\API\App\GetSearchController', 'redoReplaceAll' ] );
route( '/api/app/undo-replace-all', 'POST', [ 'Controller\API\App\GetSearchController', 'undoReplaceAll' ] );
route( '/api/app/update-job-keys', 'POST', [ 'Controller\API\App\UpdateJobKeysController', 'update' ] );
route( '/api/app/set-translation', 'POST', [ 'Controller\API\App\SetTranslationController', 'translate' ] );
route( '/api/app/split-segment', 'POST', ['Controller\API\App\SplitSegmentController', 'split' ] );
route( '/api/app/change-job-status', 'POST', ['Controller\API\App\ChangeJobsStatusController', 'changeStatus' ] );
route( '/api/app/download-tmx', 'POST', [ 'Controller\API\App\RequestExportTMXController', 'download' ] );
route( '/api/app/new-project', 'POST', ['Controller\API\App\CreateProjectController', 'create' ] );
route( '/api/app/convert-file', 'POST', ['Controller\API\App\ConvertFileController', 'handle' ] );
route( '/api/app/set-chunk-completed', 'POST', ['Controller\API\App\SetChunkCompletedController', 'complete' ] );
route( '/api/app/download-analysis-report', 'POST', ['Controller\API\App\DownloadAnalysisReportController', 'download' ] );

// Metadata
$klein->with( '/api/app/jobs/[:id_job]/[:password]/metadata', function () {
    route( '', 'GET', [ '\Controller\API\App\JobMetadataController', 'get' ] );
    route( '', 'POST', [ '\Controller\API\App\JobMetadataController', 'save' ] );
    route( '/[:key]', 'DELETE', [ '\Controller\API\App\JobMetadataController', 'delete' ] );
} );
