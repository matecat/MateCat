<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:08
 */


$klein->with('/api/v2/projects/[:id_project]/[:password]', function() {

    route( '/urls',                 'GET',  'API\V2\UrlsController',        'urls'      );
    route( '/jobs/[:id_job]/merge', 'POST', 'API\V2\JobMergeController',    'merge'     );

});

$klein->with('/api/v2/jobs/[:id_job]/[:password]', function() {
    // TODO: group all similarly prefixed APIs into this block
    route( '/comments',     'GET', 'API\V2\CommentsController', 'index' );

    /**
     * This should be moved in plugin space
     */
    route( '/quality-report', 'GET',
        'Features\ReviewImproved\Controller\API\QualityReportController', 'show'
    );
});

route(
    '/api/v2/project-completion-status/[i:id_project]', 'GET',
    '\API\V2\ProjectCompletionStatus', 'status'
);

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

