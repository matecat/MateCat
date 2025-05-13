<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/09/2018
 * Time: 18:00
 */
global $klein;

$klein->with( '/api/v3/projects', function () {
    route( '/analysis/status/[i:id_project]/[:password]', 'GET', [ '\API\V3\StatusController', 'index' ] );
} );

$klein->with('/api/v3/projects/[i:id_project]/[:password]', function() {
    route( '/cancel', 'POST', [ 'API\V3\ChangeProjectStatusController', 'cancel' ] );
    route( '/delete', 'POST', [ 'API\V3\ChangeProjectStatusController', 'delete' ] );
    route( '/archive', 'POST', [ 'API\V3\ChangeProjectStatusController', 'archive' ] );
    route( '/active', 'POST', [ 'API\V3\ChangeProjectStatusController', 'active' ] );
});

$klein->with( '/api/v3/jobs/[i:id_job]/[:password]', function () {
    route( '', 'GET', [ '\API\V3\ChunkController', 'show' ] ); //this does not show some info like teams and translators
    route( '/quality-report/segments', 'GET', [ 'Features\ReviewExtended\Controller\API\QualityReportController', 'segments' ] );
    route( '/files', 'GET', [ '\API\V3\FileInfoController', 'getInfo' ] );
    route( '/file/[i:id_file]/instructions', 'GET', [ '\API\V3\FileInfoController', 'getInstructions' ] );
    route( '/file/[i:id_file]/[:id_file_parts]/instructions', 'GET', [ '\API\V3\FileInfoController', 'getInstructionsByFilePartsId' ] );
    route( '/file/[i:id_file]/instructions', 'POST', [ '\API\V3\FileInfoController', 'setInstructions' ] );
    route( '/metadata', 'GET', [ '\API\V3\MetaDataController', 'index' ] );

    route( '/delete', 'POST', [ 'API\V3\ChangeJobStatusController', 'delete' ] );
    route( '/cancel', 'POST', [ 'API\V3\ChangeJobStatusController', 'cancel' ] );
    route( '/archive', 'POST', [ 'API\V3\ChangeJobStatusController', 'archive' ] );
    route( '/active', 'POST', [ 'API\V3\ChangeJobStatusController', 'active' ] );
} );

$klein->with( '/api/v3/teams', function () {
    route( '/[i:id_team]/projects', 'GET', [ '\API\V3\TeamsProjectsController', 'getPaginated' ] );
} );

route( '/api/v3/word-count/raw', 'POST', [ '\API\V3\CountWordController', 'rawWords' ] );
route( '/api/v3/jobs/[i:id_job]/[:password]/[:source_page]/issue-report/segments', 'GET', [ '\API\V3\IssueCheckController', 'segments' ] );
route( '/api/v3/feedback', 'POST', [ '\API\V3\RevisionFeedbackController', 'feedback' ] );
route( '/api/v3/qr/download', 'POST', [ '\API\V3\DownloadQRController', 'download' ] );

$klein->with( '/api/v3/qa_model_template', function () {
    route( '/schema', 'GET', [ '\API\V3\QAModelTemplateController', 'schema' ] );
    route( '/validate', 'POST', [ '\API\V3\QAModelTemplateController', 'validate' ] );
    route( '', 'GET', [ '\API\V3\QAModelTemplateController', 'index' ] );
    route( '', 'POST', [ '\API\V3\QAModelTemplateController', 'create' ] );
    route( '/[i:id]', 'GET', [ '\API\V3\QAModelTemplateController', 'view' ] );
    route( '/[i:id]', 'DELETE', [ '\API\V3\QAModelTemplateController', 'delete' ] );
    route( '/[i:id]', 'PUT', [ '\API\V3\QAModelTemplateController', 'edit' ] );
} );

$klein->with( '/api/v3/payable_rate', function () {
    route( '/schema', 'GET', [ '\API\V3\PayableRateController', 'schema' ] );
    route( '/validate', 'POST', [ '\API\V3\PayableRateController', 'validate' ] );
    route( '', 'GET', [ '\API\V3\PayableRateController', 'index' ] );
    route( '', 'POST', [ '\API\V3\PayableRateController', 'create' ] );
    route( '/[i:id]', 'GET', [ '\API\V3\PayableRateController', 'view' ] );
    route( '/[i:id]', 'DELETE', [ '\API\V3\PayableRateController', 'delete' ] );
    route( '/[i:id]', 'PUT', [ '\API\V3\PayableRateController', 'edit' ] );
} );

// TM Keys
$klein->with( '/api/v3/tm-keys', function () {
    route( '/list', 'GET', [ '\API\V3\TmKeyManagementController', 'getByUser' ] );
} );

route( '/api/v3/projects/[i:id_project]/[:password]/segment-analysis',  'GET',  [ 'API\V3\SegmentAnalysisController', 'project' ] );
route( '/api/v3/jobs/[i:id_job]/[:password]/segment-analysis',          'GET',  [ 'API\V3\SegmentAnalysisController', 'job' ] );
route( '/api/v3/create-key',  'POST', [ 'API\V3\MyMemoryController', 'create' ] );

// MMT
$klein->with( '/api/v3/mmt/[i:engineId]', function () {
    route( '/keys', 'GET', [ '\API\V3\ModernMTController', 'keys' ] );
    route( '/import-status/[:uuid]', 'GET', [ '\API\V3\ModernMTController', 'importStatus' ] );
    route( '/memory/create', 'POST', [ '\API\V3\ModernMTController', 'createMemory' ] );
    route( '/memory/update/[:memoryId]', 'POST', [ '\API\V3\ModernMTController', 'updateMemory' ] );
    route( '/memory/delete/[:memoryId]', 'GET', [ '\API\V3\ModernMTController', 'deleteMemory' ] );

    route( '/glossary/create-memory-and-import', 'POST', [ '\API\V3\ModernMTController', 'createMemoryAndImportGlossary' ] );
    route( '/glossary/import-status/[:uuid]', 'GET', [ '\API\V3\ModernMTController', 'importStatus' ] );
    route( '/glossary/import', 'POST', [ '\API\V3\ModernMTController', 'importGlossary' ] );
    route( '/glossary/modify', 'POST', [ '\API\V3\ModernMTController', 'modifyGlossary' ] );
} );

// DEEPL
$klein->with( '/api/v3/deepl/[:engineId]', function () {
    route( '/glossaries', 'GET', [ '\API\V3\DeepLGlossaryController', 'all' ] );
    route( '/glossaries', 'POST', [ '\API\V3\DeepLGlossaryController', 'create' ] );
    route( '/glossaries/[i:id]', 'DELETE', [ '\API\V3\DeepLGlossaryController', 'delete' ] );
    route( '/glossaries/[i:id]', 'GET', [ '\API\V3\DeepLGlossaryController', 'get' ] );
    route( '/glossaries/[i:id]/entries', 'GET', [ '\API\V3\DeepLGlossaryController', 'getEntries' ] );
} );

// PROJECT TEMPLATE
$klein->with( '/api/v3/project-template', function () {
    route( '/schema', 'GET', [ '\API\V3\ProjectTemplateController', 'schema' ] );
    route( '/', 'GET', [ '\API\V3\ProjectTemplateController', 'all' ] );
    route( '/', 'POST', [ '\API\V3\ProjectTemplateController', 'create' ] );
    route( '/[i:id]', 'DELETE', [ '\API\V3\ProjectTemplateController', 'delete' ] );
    route( '/[i:id]', 'PUT', [ '\API\V3\ProjectTemplateController', 'update' ] );
    route( '/[i:id]', 'GET', [ '\API\V3\ProjectTemplateController', 'get' ] );
} );

// FILTERS AND XLIFF CONFIG
$klein->with( '/api/v3/xliff-config-template', function () {
    route( '/schema', 'GET', [ '\API\V3\XliffConfigTemplateController', 'schema' ] );
    route( '/', 'GET', [ '\API\V3\XliffConfigTemplateController', 'all' ] );
    route( '/', 'POST', [ '\API\V3\XliffConfigTemplateController', 'create' ] );
    route( '/[i:id]', 'DELETE', [ '\API\V3\XliffConfigTemplateController', 'delete' ] );
    route( '/[i:id]', 'PUT', [ '\API\V3\XliffConfigTemplateController', 'update' ] );
    route( '/[i:id]', 'GET', [ '\API\V3\XliffConfigTemplateController', 'get' ] );
} );

$klein->with( '/api/v3/filters-config-template', function () {
    route( '/schema', 'GET', [ '\API\V3\FiltersConfigTemplateController', 'schema' ] );
    route( '/', 'GET', [ '\API\V3\FiltersConfigTemplateController', 'all' ] );
    route( '/', 'POST', [ '\API\V3\FiltersConfigTemplateController', 'create' ] );
    route( '/[i:id]', 'DELETE', [ '\API\V3\FiltersConfigTemplateController', 'delete' ] );
    route( '/[i:id]', 'PUT', [ '\API\V3\FiltersConfigTemplateController', 'update' ] );
    route( '/[i:id]', 'GET', [ '\API\V3\FiltersConfigTemplateController', 'get' ] );
} );

/**
 ***************************************************************************
 * ALIAS FOR V2 ROUTES
 ***************************************************************************
 */

$klein->with( '/api/v3/projects/[:id_project]/[:password]', function () {
    route( '', 'GET', [ 'API\V2\ProjectsController', 'get' ] ); //this do not show some info like teams and translators
    route( '/urls', 'GET', [ 'API\V2\UrlsController', 'urls' ] );
    route( '/jobs/[:id_job]/merge', 'POST', [ 'API\V2\JobMergeController', 'merge' ] );
    route( '/jobs/[:id_job]/[:job_password]/split/[:num_split]/check', 'POST', [ 'API\V2\SplitJobController', 'check' ] );
    route( '/jobs/[:id_job]/[:job_password]/split/[:num_split]/apply', 'POST', [ 'API\V2\SplitJobController', 'apply' ] );
    route( '/creation_status', 'GET', [ 'API\V2\ProjectCreationStatusController', 'get' ] );
    route( '/completion_status', 'GET', [ 'API\V2\ProjectCompletionStatus', 'status' ] );
    route( '/due_date', 'PUT', [ 'API\V2\ProjectsController', 'updateDueDate' ] );
    route( '/due_date', 'POST', [ 'API\V2\ProjectsController', 'setDueDate' ] );
    route( '/due_date', 'DELETE', [ 'API\V2\ProjectsController', 'deleteDueDate' ] );
    route( '/cancel', 'POST', [ 'API\V2\ProjectsController', 'cancel' ] );
    route( '/archive', 'POST', [ 'API\V2\ProjectsController', 'archive' ] );
    route( '/active', 'POST', [ 'API\V2\ProjectsController', 'active' ] );
    route( '/r2', 'POST', [ 'API\V2\ReviewsController', 'createReview' ] );
    route( '/analysis/status', 'GET', [ '\API\V3\StatusController', 'index' ] );
    route( '/change-name', 'POST', [ 'API\V2\ChangeProjectNameController', 'changeName' ] );
} );

$klein->with('/api/v3/activity', function () {

    route( '/project/[:id_project]/[:password]', 'GET', ['\API\V2\ActivityLogController', 'allOnProject'] );
    route( '/project/[:id_project]/[:password]/last', 'GET', ['\API\V2\ActivityLogController', 'lastOnProject'] );
    route( '/job/[:id_job]/[:password]/last', 'GET', ['API\V2\ActivityLogController', 'lastOnJob'] );

} );

$klein->with( '/api/v3/jobs/[:id_job]/[:password]', function () {

    route( '', 'GET', [ 'API\V2\ChunkController', 'show' ] );
    route( '/comments', 'GET', [ 'API\V2\CommentsController', 'index' ] );

    route( '/quality-report', 'GET', [ 'Features\ReviewExtended\Controller\API\QualityReportController', 'show' ] );
    route( '/quality-report/general', 'GET', [ 'Features\ReviewExtended\Controller\API\QualityReportController', 'general' ] );

    route( '/translator', 'GET', [ '\API\V2\JobsTranslatorsController', 'get' ] );
    route( '/translator', 'POST', [ '\API\V2\JobsTranslatorsController', 'add' ] );

    route( '/translation-issues', 'GET', [ 'API\V2\ChunkTranslationIssueController', 'index' ] );
    route( '/translation-versions', 'GET', [ '\API\V2\ChunkTranslationVersionController', 'index' ] );

    route( '/revise/segments/[:id_segment]/translation-versions', 'GET', [ '\API\V2\ReviseTranslationIssuesController', 'index' ] );

    route( '/segments/[:id_segment]/translation-versions', 'GET', [ '\API\V2\SegmentVersion', 'index' ] );
    route( '/segments/[:id_segment]/translation-versions/[:version_number]', 'GET', [ '\API\V2\SegmentVersion', 'detail' ] );

    route( '/segments/[:id_segment]/translation-issues', 'POST', [ 'API\V2\SegmentTranslationIssueController', 'create' ] );
    route( '/segments/[:id_segment]/translation-issues/[:id_issue]', 'DELETE', [ 'API\V2\SegmentTranslationIssueController', 'delete' ] );
    route( '/segments/[:id_segment]/translation-issues/[:id_issue]', 'POST', [ 'API\V2\SegmentTranslationIssueController', 'update' ] );
    route( '/segments/[:id_segment]/translation-issues/[:id_issue]/comments', 'POST', [ 'API\V2\SegmentTranslationIssueController', 'createComment' ] );
    route( '/segments/[:id_segment]/translation-issues/[:id_issue]/comments', 'GET', [ 'API\V2\SegmentTranslationIssueController', 'getComments' ] );

    route( '/segments/status', 'POST', [ '\API\V2\JobStatusController', 'changeSegmentsStatus' ] ); // mark as translated bulk

    route( '/segments-filter', 'GET', [ 'Features\SegmentFilter\Controller\API\FilterController', 'index' ] );

    route( '/options', 'POST', [ 'API\V2\ChunkOptionsController', 'update' ] );


    route( '/delete', 'POST', [ 'API\V2\ChunkController', 'delete' ] );
    route( '/cancel', 'POST', [ 'API\V2\ChunkController', 'cancel' ] );
    route( '/archive', 'POST', [ 'API\V2\ChunkController', 'archive' ] );
    route( '/active', 'POST', [ 'API\V2\ChunkController', 'active' ] );

} );

$klein->with( '/api/v3/glossaries', function () {

    route( '/check/', 'POST', [ '\API\V2\GlossariesController', 'check' ] );
    route( '/import/', 'POST', [ '\API\V2\GlossariesController', 'import' ] );
    route( '/import/status/[:uuid]', 'GET', [ '\API\V2\GlossariesController', 'uploadStatus' ] );
    route( '/export/', 'POST', [ '\API\V2\GlossariesController', 'download' ] );

} );

route( '/api/v3/ping', 'HEAD', [ '\API\V2\KeyCheckController', 'ping' ] );

route( '/api/v3/user/[:user_api_key]', 'GET', [ '\API\V2\KeyCheckController', 'getUID' ] );
route( '/api/v3/keys/list', 'GET', [ '\API\V2\MemoryKeysController', 'listKeys' ] );
route( '/api/v3/engines/list', 'GET', [ '\API\V2\EnginesController', 'listEngines' ] );

$klein->with( '/api/v3/teams', function () {

    route( '', 'GET', [ '\API\V2\TeamsController', 'getTeamList' ] );
    route( '', 'POST', [ '\API\V2\TeamsController', 'create' ] );
    route( '/[i:id_team]', 'PUT', [ '\API\V2\TeamsController', 'update' ] );

    route( '/[i:id_team]/members', 'POST', [ '\API\V2\TeamMembersController', 'update' ] );
    route( '/[i:id_team]/members', 'GET', [ '\API\V2\TeamMembersController', 'index' ] );
    route( '/[i:id_team]/members/[i:uid_member]', 'DELETE', [ '\API\V2\TeamMembersController', 'delete' ] );

    route( '/[i:id_team]/projects/[i:id_project]', 'PUT', [ 'API\V2\TeamsProjectsController', 'update' ] );
    route( '/[i:id_team]/projects/[i:id_project]', 'GET', [ 'API\V2\TeamsProjectsController', 'get' ] );
    route( '/[i:id_team]/projects/[:project_name]', 'GET', [ 'API\V2\TeamsProjectsController', 'getByName' ] );

} );

$klein->with( '/api/v3/languages', function () {
    route( '', 'GET', [ '\API\V2\SupportedLanguagesController', 'index' ] );
} );

$klein->with( '/api/v3/files', function () {
    route( '', 'GET', [ '\API\V2\SupportedFilesController', 'index' ] );
} );

// change password
route( '/api/v3/change-password', 'POST', [ 'API\V2\ChangePasswordController', 'changePassword' ] );

// Download files
route( '/api/v3/original/[:id_job]/[:password]', 'GET', [ 'API\V2\DownloadOriginalController', 'index' ] );
route( '/api/v3/translation/[:id_job]/[:password]', 'GET', [ 'API\V2\DownloadFileController', 'index' ] );
route( '/api/v3/SDLXLIFF/[:id_job]/[:password]/[:filename]', 'GET', [ 'API\V2\DownloadFileController', 'forceXliff' ] );
route( '/api/v3/TMX/[:id_job]/[:password]', 'GET', [ 'API\V2\ExportTMXController', 'index' ] );

// User
route('/api/v3/user', 'PUT',  ['API\V2\UserController', 'edit']);
route('/api/v3/user/metadata', 'PUT',  ['API\V2\UserController', 'setMetadata']);
