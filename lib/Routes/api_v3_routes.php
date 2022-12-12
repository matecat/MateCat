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
    route( '', 'GET', '\API\V3\ChunkController', 'show' ); //this do not show some info like teams and translators
    route( '/quality-report/segments', 'GET', 'Features\ReviewExtended\Controller\API\QualityReportController', 'segments' );
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

// TM Keys
$klein->with( '/api/v3/tm-keys', function () {
    route( '/list', 'GET', '\API\V3\TmKeyManagementController', 'getByUser' );
} );
