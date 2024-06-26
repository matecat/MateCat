<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/09/2018
 * Time: 18:00
 */

$klein->with( '/api/v3/projects', function () {
    route( '/analysis/status/[:id_project]/[:password]', 'GET', '\API\V3\StatusController', 'index' );
} );

$klein->with('/api/v3/projects/[:id_project]/[:password]', function() {
    route( '/cancel', 'POST', 'API\V3\ChangeProjectStatusController', 'cancel' );
    route( '/delete', 'POST', 'API\V3\ChangeProjectStatusController', 'delete' );
    route( '/archive', 'POST', 'API\V3\ChangeProjectStatusController', 'archive' );
    route( '/active', 'POST', 'API\V3\ChangeProjectStatusController', 'active' );
});

$klein->with( '/api/v3/jobs/[:id_job]/[:password]', function () {
    route( '', 'GET', '\API\V3\ChunkController', 'show' ); //this does not show some info like teams and translators
    route( '/quality-report/segments', 'GET', 'Features\SecondPassReview\Controller\API\QualityReportController', 'segments' );
    route( '/files', 'GET', '\API\V3\FileInfoController', 'getInfo' );
    route( '/file/[:id_file]/instructions', 'GET', '\API\V3\FileInfoController', 'getInstructions' );
    route( '/file/[:id_file]/[:id_file_parts]/instructions', 'GET', '\API\V3\FileInfoController', 'getInstructionsByFilePartsId' );
    route( '/file/[:id_file]/instructions', 'POST', '\API\V3\FileInfoController', 'setInstructions' );
    route( '/metadata', 'GET', '\API\V3\MetaDataController', 'index' );

    route( '/delete', 'POST', 'API\V3\ChangeJobStatusController', 'delete' );
    route( '/cancel', 'POST', 'API\V3\ChangeJobStatusController', 'cancel' );
    route( '/archive', 'POST', 'API\V3\ChangeJobStatusController', 'archive' );
    route( '/active', 'POST', 'API\V3\ChangeJobStatusController', 'active' );
} );

$klein->with( '/api/v3/teams', function () {
    route( '/[i:id_team]/projects', 'GET', '\API\V3\TeamsProjectsController', 'getPaginated' );
} );

route( '/api/v3/word-count/raw', 'POST', '\API\V3\CountWordController', 'rawWords' );
route( '/api/v3/jobs/[:id_job]/[:password]/[:source_page]/issue-report/segments', 'GET', '\API\V3\IssueCheckController', 'segments' );
route( '/api/v3/feedback', 'POST', '\API\V3\RevisionFeedbackController', 'feedback' );
route( '/api/v3/qr/download', 'POST', '\API\V3\DownloadQRController', 'download' );

$klein->with( '/api/v3/glossary', function () {
    route( '/blacklist/upload', 'POST', '\API\V3\BlacklistController', 'upload' );
    route( '/blacklist/delete/[:id_file]', 'DELETE', '\API\V3\BlacklistController', 'delete' );
    route( '/blacklist/get/[:id_file]', 'GET', '\API\V3\BlacklistController', 'get' );
} );

$klein->with( '/api/v3/qa_model_template', function () {
    route( '/schema', 'GET', '\API\V3\QAModelTemplateController', 'schema' );
    route( '/validate', 'POST', '\API\V3\QAModelTemplateController', 'validate' );
    route( '', 'GET', '\API\V3\QAModelTemplateController', 'index' );
    route( '', 'POST', '\API\V3\QAModelTemplateController', 'create' );
    route( '/[:id]', 'GET', '\API\V3\QAModelTemplateController', 'view' );
    route( '/[:id]', 'DELETE', '\API\V3\QAModelTemplateController', 'delete' );
    route( '/[:id]', 'PUT', '\API\V3\QAModelTemplateController', 'edit' );
} );

$klein->with( '/api/v3/payable_rate', function () {
    route( '/schema', 'GET', '\API\V2\PayableRateController', 'schema' );
    route( '/validate', 'POST', '\API\V2\PayableRateController', 'validate' );
    route( '', 'GET', '\API\V2\PayableRateController', 'index' );
    route( '', 'POST', '\API\V2\PayableRateController', 'create' );
    route( '/[:id]', 'GET', '\API\V2\PayableRateController', 'view' );
    route( '/[:id]', 'DELETE', '\API\V2\PayableRateController', 'delete' );
    route( '/[:id]', 'PUT', '\API\V2\PayableRateController', 'edit' );
} );

// TM Keys
$klein->with( '/api/v3/tm-keys', function () {
    route( '/list', 'GET', '\API\V3\TmKeyManagementController', 'getByUser' );
} );

route( '/api/v3/projects/[:id_project]/[:password]/segment-analysis',  'GET',  'API\V3\SegmentAnalysisController', 'project' );
route( '/api/v3/jobs/[:id_job]/[:password]/segment-analysis',          'GET',  'API\V3\SegmentAnalysisController', 'job' );
route( '/api/v3/create-key',  'POST', 'API\V3\MyMemoryController', 'create' );

// MMT
$klein->with( '/api/v3/mmt/[:engineId]', function () {
    route( '/keys', 'GET', '\API\V3\ModernMTController', 'keys' );
    route( '/job-status/[:uuid]', 'GET', '\API\V3\ModernMTController', 'jobStatus' );
    route( '/create-memory-and-import-glossary', 'POST', '\API\V3\ModernMTController', 'createMemoryAndImportGlossary' );
    route( '/import-glossary', 'POST', '\API\V3\ModernMTController', 'importGlossary' );
    route( '/modify-glossary', 'POST', '\API\V3\ModernMTController', 'modifyGlossary' );
    route( '/create-memory', 'POST', '\API\V3\ModernMTController', 'createMemory' );
    route( '/update-memory/[:memoryId]', 'POST', '\API\V3\ModernMTController', 'updateMemory' );
    route( '/delete-memory/[:memoryId]', 'GET', '\API\V3\ModernMTController', 'deleteMemory' );
} );

// DEEPL
$klein->with( '/api/v3/deepl/[:engineId]', function () {
    route( '/glossaries', 'GET', '\API\V3\DeepLGlossaryController', 'all' );
    route( '/glossaries', 'POST', '\API\V3\DeepLGlossaryController', 'create' );
    route( '/glossaries/[:id]', 'DELETE', '\API\V3\DeepLGlossaryController', 'delete' );
    route( '/glossaries/[:id]', 'GET', '\API\V3\DeepLGlossaryController', 'get' );
    route( '/glossaries/[:id]/entries', 'GET', '\API\V3\DeepLGlossaryController', 'getEntries' );
} );

// PROJECT TEMPLATE
$klein->with( '/api/v3/project-template', function () {
    route( '/schema', 'GET', '\API\V3\ProjectTemplateController', 'schema' );
    route( '/', 'GET', '\API\V3\ProjectTemplateController', 'all' );
    route( '/', 'POST', '\API\V3\ProjectTemplateController', 'create' );
    route( '/[:id]', 'DELETE', '\API\V3\ProjectTemplateController', 'delete' );
    route( '/[:id]', 'PUT', '\API\V3\ProjectTemplateController', 'update' );
    route( '/[:id]', 'GET', '\API\V3\ProjectTemplateController', 'get' );
} );
