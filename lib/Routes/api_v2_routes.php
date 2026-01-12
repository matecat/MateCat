<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:08
 */

global $klein;

$klein->with('/api/v2/projects/[:id_project]/[:password]', function () {
    route('', 'GET', ['Controller\API\V2\ProjectsController', 'get']); //this do not show some info like teams and translators
    route('/urls', 'GET', ['Controller\API\V2\UrlsController', 'urls']);
    route('/jobs/[:id_job]/merge', 'POST', ['Controller\API\V2\JobMergeController', 'merge']);
    route('/jobs/[:id_job]/[:job_password]/split/[:num_split]/check', 'POST', ['Controller\API\V2\SplitJobController', 'check']);
    route('/jobs/[:id_job]/[:job_password]/split/[:num_split]/apply', 'POST', ['Controller\API\V2\SplitJobController', 'apply']);
    route('/creation_status', 'GET', ['Controller\API\V2\ProjectCreationStatusController', 'get']);
    route('/completion_status', 'GET', ['Controller\API\V2\ProjectCompletionStatus', 'status']);
    route('/due_date', 'PUT', ['Controller\API\V2\ProjectsController', 'updateDueDate']);
    route('/due_date', 'POST', ['Controller\API\V2\ProjectsController', 'setDueDate']);
    route('/due_date', 'DELETE', ['Controller\API\V2\ProjectsController', 'deleteDueDate']);
    route('/cancel', 'POST', ['Controller\API\V2\ProjectsController', 'cancel']);
    route('/archive', 'POST', ['Controller\API\V2\ProjectsController', 'archive']);
    route('/active', 'POST', ['Controller\API\V2\ProjectsController', 'active']);
    route('/r2', 'POST', ['Controller\API\V2\ReviewsController', 'createReview']);
    route('/analysis/status', 'GET', ['\Controller\API\V3\StatusController', 'index']);

    // change project name
    route('/change-name', 'POST', ['Controller\API\V2\ChangeProjectNameController', 'changeName']);
});

$klein->with('/api/v2/activity', function () {
    route('/project/[:id_project]/[:password]', 'GET', ['\Controller\API\V2\ActivityLogController', 'allOnProject']);
    route('/project/[:id_project]/[:password]/last', 'GET', ['\Controller\API\V2\ActivityLogController', 'lastOnProject']);
    route('/job/[:id_job]/[:password]/last', 'GET', ['Controller\API\V2\ActivityLogController', 'lastOnJob']);
});

$klein->with('/api/v2/jobs/[:id_job]/[:password]', function () {
    route('', 'GET', ['Controller\API\V2\JobsController', 'show']);
    route('/comments', 'GET', ['Controller\API\V2\CommentsController', 'index']);

    route('/quality-report', 'GET', ['Controller\API\V3\QualityReportControllerAPI', 'show']);
    route('/quality-report/general', 'GET', ['Controller\API\V3\QualityReportControllerAPI', 'general']);

    route('/translator', 'GET', ['\Controller\API\V2\JobsTranslatorsController', 'get']);
    route('/translator', 'POST', ['\Controller\API\V2\JobsTranslatorsController', 'add']);

    route('/translation-issues', 'GET', ['Controller\API\V2\ChunkTranslationIssueController', 'index']);
    route('/translation-versions', 'GET', ['\Controller\API\V2\ChunkTranslationVersionController', 'index']);

    route('/revise/segments/[:id_segment]/translation-versions', 'GET', ['\Controller\API\V2\ReviseTranslationIssuesController', 'index']);

    route('/segments/[:id_segment]/translation-versions', 'GET', ['\Controller\API\V2\SegmentVersionController', 'index']);
    route('/segments/[:id_segment]/translation-versions/[:version_number]', 'GET', ['\Controller\API\V2\SegmentVersionController', 'detail']);

    route('/segments/[:id_segment]/translation-issues', 'POST', ['Controller\API\V2\SegmentTranslationIssueController', 'create']);
    route('/segments/[:id_segment]/translation-issues/[:id_issue]', 'DELETE', ['Controller\API\V2\SegmentTranslationIssueController', 'delete']);
    route('/segments/[:id_segment]/translation-issues/[:id_issue]', 'POST', ['Controller\API\V2\SegmentTranslationIssueController', 'update']);
    route('/segments/[:id_segment]/translation-issues/[:id_issue]/comments', 'POST', ['Controller\API\V2\SegmentTranslationIssueController', 'createComment']);
    route('/segments/[:id_segment]/translation-issues/[:id_issue]/comments', 'GET', ['Controller\API\V2\SegmentTranslationIssueController', 'getComments']);

    route('/segments/status', 'POST', ['\Controller\API\V2\MarkAllSegmentStatusController', 'changeSegmentsStatus']); // mark as translated bulk

    route('/segments-filter', 'GET', ['Plugins\Features\SegmentFilter\Controller\API\FilterController', 'index']);

    route('/delete', 'POST', ['Controller\API\V2\JobsController', 'delete']);
    route('/cancel', 'POST', ['Controller\API\V2\JobsController', 'cancel']);
    route('/archive', 'POST', ['Controller\API\V2\JobsController', 'archive']);
    route('/active', 'POST', ['Controller\API\V2\JobsController', 'active']);
});

$klein->with('/api/v2/glossaries', function () {
    route('/check/', 'POST', ['\Controller\API\V2\GlossaryFilesController', 'check']);
    route('/import/', 'POST', ['\Controller\API\V2\GlossaryFilesController', 'import']);
    route('/import/status/[:uuid]', 'GET', ['\Controller\API\V2\GlossaryFilesController', 'importStatus']);
    route('/export/', 'POST', ['\Controller\API\V2\GlossaryFilesController', 'download']);
});

route('/api/v2/ping', 'HEAD', ['\Controller\API\V2\KeyCheckController', 'ping']);

route('/api/v2/user/[:user_api_key]', 'GET', ['\Controller\API\V2\KeyCheckController', 'getUID']);
route('/api/v2/keys/list', 'GET', ['\Controller\API\V2\MemoryKeysController', 'listKeys']);
route('/api/v2/engines/list', 'GET', ['\Controller\API\V2\EnginesController', 'listEngines']);

$klein->with('/api/v2/teams', function () {
    route('', 'GET', ['\Controller\API\V2\TeamsController', 'getTeamList']);
    route('', 'POST', ['\Controller\API\V2\TeamsController', 'create']);
    route('/[i:id_team]', 'PUT', ['\Controller\API\V2\TeamsController', 'update']);

    route('/[i:id_team]/members', 'POST', ['\Controller\API\V2\TeamMembersController', 'update']);
    route('/[i:id_team]/members', 'GET', ['\Controller\API\V2\TeamMembersController', 'index']);
    route('/[i:id_team]/members/[i:uid_member]', 'DELETE', ['\Controller\API\V2\TeamMembersController', 'delete']);

    route('/[i:id_team]/projects/[i:id_project]', 'PUT', ['Controller\API\V2\TeamsProjectsController', 'update']);
    route('/[i:id_team]/projects/[i:id_project]', 'GET', ['Controller\API\V2\TeamsProjectsController', 'get']);
    route('/[i:id_team]/projects/[:project_name]', 'GET', ['Controller\API\V2\TeamsProjectsController', 'getByName']);
});

$klein->with('/api/v2/languages', function () {
    route('', 'GET', ['\Controller\API\V2\SupportedLanguagesController', 'index']);
});


$klein->with('/api/v2/files', function () {
    route('', 'GET', ['\Controller\API\V2\SupportedFilesController', 'index']);
});

$klein->with('/api/v2/payable_rate', function () {
    route('/schema', 'GET', ['\Controller\API\V3\PayableRateController', 'schema']);
    route('/validate', 'POST', ['\Controller\API\V3\PayableRateController', 'validate']);
    route('', 'GET', ['\Controller\API\V3\PayableRateController', 'index']);
    route('', 'POST', ['\Controller\API\V3\PayableRateController', 'create']);
    route('/[:id]', 'GET', ['\Controller\API\V3\PayableRateController', 'view']);
    route('/[:id]', 'DELETE', ['\Controller\API\V3\PayableRateController', 'delete']);
    route('/[:id]', 'PUT', ['\Controller\API\V3\PayableRateController', 'edit']);
});

// change password
route('/api/v2/change-password', 'POST', ['Controller\API\V2\ChangePasswordController', 'changePassword']);

// Download files
route('/api/v2/original/[:id_job]/[:password]', 'GET', ['Controller\API\V2\DownloadOriginalController', 'index']);
route('/api/v2/translation/[:id_job]/[:password]', 'GET', ['Controller\API\V2\DownloadController', 'index']);
route('/api/v2/xliff/[:id_job]/[:password]/[:filename]', 'GET', ['Controller\API\V2\DownloadController', 'forceXliff']);
route('/api/v2/tmx/[:id_job]/[:password]', 'GET', ['Controller\API\V2\DownloadJobTMXController', 'index']);

// User
route('/api/v2/user', 'PUT', ['Controller\API\V2\UserController', 'edit']);
route('/api/v2/user/metadata', 'PUT', ['Controller\API\V2\UserController', 'setMetadata']);
