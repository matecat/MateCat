<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:08
 */

$klein->with('/api/v2/projects/[:id_project]/[:password]', function() {
    route( '',                      'GET',  'API\V2\ProjectsController',    'get'     ); //this do not show some info like teams and translators
    route( '/urls',                 'GET',  'API\V2\UrlsController',        'urls'      );
    route( '/jobs/[:id_job]/merge', 'POST', 'API\V2\JobMergeController',    'merge'     );
    route( '/jobs/[:id_job]/[:job_password]/split/[:num_split]/check', 'POST', 'API\V2\JobSplitController', 'check' );
    route( '/jobs/[:id_job]/[:job_password]/split/[:num_split]/apply', 'POST', 'API\V2\JobSplitController', 'apply' );
    route( '/creation_status', 'GET', 'API\V2\ProjectCreationStatusController', 'get' );
    route( '/completion_status', 'GET', 'API\V2\ProjectCompletionStatus', 'status' );
    route( '/due_date', 'PUT', 'API\V2\ProjectsController', 'updateDueDate' );
    route( '/due_date', 'POST', 'API\V2\ProjectsController', 'setDueDate' );
    route( '/due_date', 'DELETE', 'API\V2\ProjectsController', 'deleteDueDate' );
    route( '/cancel', 'POST', 'API\V2\ProjectsController', 'cancel' );
    route( '/archive', 'POST', 'API\V2\ProjectsController', 'archive' );
    route( '/active', 'POST', 'API\V2\ProjectsController', 'active' );
    route( '/r2', 'POST', 'API\V2\ReviewsController', 'createReview' );
});

route( '/api/v2/project-completion-status/[i:id_project]', 'GET', '\API\V2\ProjectCompletionStatus', 'status' );


$klein->with('/api/v2/activity', function() {

    route( '/project/[:id_project]/[:password]/last', 'GET', '\API\V2\ActivityLogController', 'lastOnProject' );
    route( '/job/[:id_job]/[:password]/last', 'GET', 'API\V2\ActivityLogController', 'lastOnJob' );

});

$klein->with('/api/v2/jobs/[:id_job]/[:password]', function() {

    route( '',              'GET', 'API\V2\ChunkController', 'show' );
    route( '/comments',     'GET', 'API\V2\CommentsController', 'index' );

    route( '/quality-report', 'GET', 'Features\SecondPassReview\Controller\API\QualityReportController', 'show' );
    route( '/quality-report/general', 'GET', 'Features\SecondPassReview\Controller\API\QualityReportController', 'general' );

    route( '/translator', 'GET',  '\API\V2\JobsTranslatorsController', 'get' ) ;
    route( '/translator', 'POST',  '\API\V2\JobsTranslatorsController', 'add' ) ;

    route( '/translation-issues', 'GET', 'API\V2\ChunkTranslationIssueController', 'index' );
    route( '/translation-versions', 'GET', '\API\V2\ChunkTranslationVersionController', 'index' );

    route( '/revise/segments/[:id_segment]/translation-versions', 'GET', '\API\V2\ReviseTranslationIssuesController', 'index' );

    route( '/segments/[:id_segment]/translation-versions', 'GET', '\API\V2\SegmentVersion', 'index' );
    route( '/segments/[:id_segment]/translation-versions/[:version_number]', 'GET', '\API\V2\SegmentVersion', 'detail' );

    route( '/segments/[:id_segment]/translation-issues', 'POST', 'API\V2\SegmentTranslationIssueController', 'create' );
    route( '/segments/[:id_segment]/translation-issues/[:id_issue]', 'DELETE', 'API\V2\SegmentTranslationIssueController', 'delete' );
    route( '/segments/[:id_segment]/translation-issues/[:id_issue]', 'POST', 'API\V2\SegmentTranslationIssueController', 'update' );
    route( '/segments/[:id_segment]/translation-issues/[:id_issue]/comments', 'POST', 'API\V2\SegmentTranslationIssueController', 'createComment' );
    route( '/segments/[:id_segment]/translation-issues/[:id_issue]/comments', 'GET', 'API\V2\SegmentTranslationIssueController', 'getComments' );

    route( '/segments/status', 'POST', '\API\V2\JobStatusController', 'changeSegmentsStatus' ); // mark as translated bulk

    route( '/segments-filter', 'GET', 'Features\SegmentFilter\Controller\API\FilterController', 'index' );

    route( '/options', 'POST', 'API\V2\ChunkOptionsController', 'update' );


    route( '/delete', 'POST', 'API\V2\ChunkController', 'delete' );
    route( '/cancel', 'POST', 'API\V2\ChunkController', 'cancel' );
    route( '/archive', 'POST', 'API\V2\ChunkController', 'archive' );
    route( '/active', 'POST', 'API\V2\ChunkController', 'active' );

});

$klein->with('/api/v2/glossaries', function() {

    route( '/check/', 'POST', '\API\V2\GlossariesController', 'check' );
    route( '/import/', 'POST', '\API\V2\GlossariesController', 'import' );
    route( '/import/status/[:uuid]', 'GET', '\API\V2\GlossariesController', 'uploadStatus' );
    route( '/export/', 'POST', '\API\V2\GlossariesController', 'download' );

});

route( '/api/v2/ping', 'HEAD', '\API\V2\KeyCheckController', 'ping' );

route( '/api/v2/user/[:user_api_key]', 'GET',  '\API\V2\KeyCheckController',   'getUID' );
route( '/api/v2/keys/list',            'GET',  '\API\V2\MemoryKeysController', 'listKeys' );
route( '/api/v2/engines/list',         'GET',  '\API\V2\EnginesController',    'listEngines' );

$klein->with('/api/v2/teams', function() {

    route( '',                                     'GET',  '\API\V2\TeamsController', 'getTeamList') ;
    route( '',                                     'POST', '\API\V2\TeamsController', 'create') ;
    route( '/[i:id_team]',                         'PUT',  '\API\V2\TeamsController', 'update' ) ;

    route( '/[i:id_team]/members',                 'POST',    '\API\V2\TeamMembersController', 'update') ;
    route( '/[i:id_team]/members',                 'GET',     '\API\V2\TeamMembersController', 'index' ) ;
    route( '/[i:id_team]/members/[i:uid_member]',  'DELETE' , '\API\V2\TeamMembersController', 'delete' );

    route( '/[i:id_team]/projects/[i:id_project]', 'PUT', 'API\V2\TeamsProjectsController',  'update' ) ;
    route( '/[i:id_team]/projects/[i:id_project]', 'GET', 'API\V2\TeamsProjectsController',  'get' ) ;
    route( '/[i:id_team]/projects/[:project_name]', 'GET', 'API\V2\TeamsProjectsController',  'getByName' ) ;
    route( '/[i:id_team]/projects',                'GET', '\API\V2\TeamsProjectsController', 'getAll') ;

}) ;

$klein->with('/api/v2/languages', function() {
    route( '', 'GET', '\API\V2\SupportedLanguagesController', 'index' );
});


$klein->with('/api/v2/files', function() {
    route( '', 'GET', '\API\V2\SupportedFilesController', 'index' );
});

$klein->with( '/api/v2/payable_rate', function () {
    route('/schema', 'GET', '\API\V2\PayableRateController', 'schema');
    route('/validate', 'POST', '\API\V2\PayableRateController', 'validate');
    route('', 'GET', '\API\V2\PayableRateController', 'index');
    route('', 'POST', '\API\V2\PayableRateController', 'create');
    route('/[:id]', 'GET', '\API\V2\PayableRateController', 'view');
    route('/[:id]', 'DELETE', '\API\V2\PayableRateController', 'delete');
    route('/[:id]', 'PUT', '\API\V2\PayableRateController', 'edit');

});

// change password
route( '/api/v2/change-password',  'POST', 'API\V2\ChangePasswordController', 'changePassword' );

// Download files
route( '/api/v2/original/[:id_job]/[:password]', 'GET',  'API\V2\DownloadOriginalController', 'index' );
route( '/api/v2/translation/[:id_job]/[:password]', 'GET',  'API\V2\DownloadFileController', 'index' );
route( '/api/v2/SDLXLIFF/[:id_job]/[:password]/[:filename]', 'GET',  'API\V2\DownloadFileController', 'forceXliff' );
route( '/api/v2/TMX/[:id_job]/[:password]', 'GET',  'API\V2\ExportTMXController', 'index' );

// User
route('/api/v2/user', 'PUT',  'API\V2\UserController', 'edit');