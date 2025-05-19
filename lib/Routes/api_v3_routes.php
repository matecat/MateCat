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

    route( '/warnings', 'GET', [ 'API\App\GetWarningController', 'global' ] );

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