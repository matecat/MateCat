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
    route( '/creation_status',      'GET',  'API\V2\ProjectCreationStatusController',   'get' );
    route( '/completion_status',    'GET',  'API\V2\ProjectCompletionStatus', 'status' ) ;

});

$klein->with('/api/v2/activity', function() {

    route(
            '/project/[:id_project]/[:password]/last', 'GET',
            '\API\V2\ActivityLogController', 'lastOnProject'
    );

    route(
            '/job/[:id_job]/[:password]/last', 'GET',
            'API\V2\ActivityLogController', 'lastOnJob'
    );

});

$klein->with('/api/v2/jobs/[:id_job]/[:password]', function() {
    route( '',              'GET', 'API\V2\ChunkController', 'show' );
    route( '/comments',     'GET', 'API\V2\CommentsController', 'index' );

    route( '/quality-report',          'GET', 'Features\ReviewImproved\Controller\API\QualityReportController', 'show' );
    route( '/quality-report/versions', 'GET', 'Features\ReviewImproved\Controller\API\QualityReportController', 'versions' );

    route( '/translator', 'GET',  '\API\V2\JobsTranslatorsController', 'get' ) ;
    route( '/translator', 'POST',  '\API\V2\JobsTranslatorsController', 'add' ) ;

});

route( '/api/v2/project-completion-status/[i:id_project]', 'GET', '\API\V2\ProjectCompletionStatus', 'status' );

route(
    '/api/v2/project-translation/[i:id_project]', 'GET',
    'API_V2_ProjectTranslation', 'status'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/translation-issues', 'GET',
    'API\V2\ChunkTranslationIssueController', 'index'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/translation-versions', 'GET',
    '\API\V2\ChunkTranslationVersionController', 'index'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation-versions', 'GET',
    '\API\V2\SegmentVersion', 'index'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation-versions/[:version_number]', 'GET',
    'API_V2_SegmentVersion', 'detail'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation-issues', 'POST',
    'API\V2\SegmentTranslationIssueController', 'create'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation-issues/[:id_issue]', 'DELETE',
    'API\V2\SegmentTranslationIssueController', 'delete'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation-issues/[:id_issue]', 'POST',
    'API\V2\SegmentTranslationIssueController', 'update'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation-issues/[:id_issue]/comments', 'POST',
    'API\V2\TranslationIssueComment', 'create'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation-issues/[:id_issue]/comments', 'GET',
    'API\V2\TranslationIssueComment', 'index'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation', 'GET',
    'API\V2\TranslationController', 'index'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/segments-filter', 'GET',
    'Features\SegmentFilter\Controller\API\FilterController', 'index'
);

route(
    '/api/v2/jobs/[:id_job]/[:password]/options', 'POST',
    'API\V2\ChunkOptionsController', 'update'
);

route(
    '/api/v2/glossaries/import/', 'POST',
    '\API\V2\GlossariesController', 'import'
);

route(
    '/api/v2/glossaries/import/status/[:tm_key].?[:name]?', 'GET',
    '\API\V2\GlossariesController', 'uploadStatus'
);

route(
    '/api/v2/glossaries/export/[:tm_key].?[:downloadToken]?', 'GET',
    '\API\V2\GlossariesController', 'download'
);

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
    route( '/[i:id_team]/projects',                'GET', '\API\V2\TeamsProjectsController', 'getAll') ;

}) ;

