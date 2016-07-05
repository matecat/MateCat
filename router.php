<?php

require_once './inc/Bootstrap.php' ;
require_once './lib/Model/queries.php' ;

Bootstrap::start();

$klein = new \Klein\Klein();

function route($path, $method, $controller, $action) {
    global $klein;

    $klein->respond($method, $path, function() use ($controller, $action) {
        $reflect = new ReflectionClass($controller);
        $instance = $reflect->newInstanceArgs(func_get_args());
        $instance->respond( $action );
    });
}

Features::loadRoutes( $klein );

$klein->onError(function ($klein, $err_msg, $err_type, $exception) {
    // TODO still need to catch fatal errors here with 500 code

    switch( $err_type ) {
        case 'API\V2\AuthenticationError':
            $klein->response()->code(401);
            break;
        case 'API\V2\AuthorizationError':
            $klein->response()->code(403);
            break;
        case 'API\V2\ValidationError':
            $klein->response()->code(400);
            $klein->response()->json( array('error' => $err_msg ));
            break;
        case 'Exceptions_RecordNotFound':
        case 'Exceptions\NotFoundError':
            \Log::doLog('Not found error for URI: ' . $_SERVER['REQUEST_URI']);
            $klein->response()->code(404);
            $klein->response()->body('not found');
            $klein->response()->send();
            break;
        default:
            $klein->response()->code(500);
            \Utils::sendErrMailReport( $exception->getMessage() . "" . $exception->getTraceAsString(), 'Generic error' );
            \Log::doLog("Error: {$exception->getMessage()} ");
            \Log::doLog( $exception->getTraceAsString() );
            break;
    }

});

// This is unreleased APIs. I'm no longer fond of the [i:id_job] in the path,
// so before releasing change it use a querystring.
//
route(
    '/api/v2/jobs/[i:id_job]/revision-data', 'GET',
    'API_V2_JobRevisionData', 'revisionData'
);

route(
    '/api/v2/jobs/[i:id_job]/revision-data/segments', 'GET',
    'API_V2_JobRevisionData', 'segments'
);

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

$klein->respond('POST', '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation-issues/[:id_issue]', function() {
    $reflect  = new ReflectionClass('API\V2\SegmentTranslationIssueController');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('update');
});

$klein->respond('POST', '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation-issues/[:id_issue]/comments', function() {
    $reflect  = new ReflectionClass('API\V2\TranslationIssueComment');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('create');
});

$klein->respond('GET', '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation-issues/[:id_issue]/comments', function() {
    $reflect  = new ReflectionClass('API\V2\TranslationIssueComment');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('index');
});

$klein->respond('GET', '/api/v2/jobs/[:id_job]/[:password]/segments/[:id_segment]/translation', function() {
    $reflect  = new ReflectionClass('API\V2\TranslationController');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('index');
});


$klein->respond('GET', '/utils/pee', function() {
    $reflect  = new ReflectionClass('peeViewController');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->doAction();
    $instance->finalize();
});

$klein->respond('POST', '/api/v2/projects/[:id_project]/[:password]/jobs/[:id_job]/merge', function() {
    $reflect  = new ReflectionClass('API\V2\JobMergeController');
    $instance = $reflect->newInstanceArgs(func_get_args());
    $instance->respond('merge');
});




route( '/api/v1/jobs/[:id_job]/[:password]/stats', 'GET',  'API\V1\StatsController', 'stats' );

route( '/api/v2/jobs/[:id_job]/[:password]/segments-filter', 'GET',
        'Features\SegmentFilter\Controller\API\FilterController', 'index'
);

route( '/api/v2/jobs/[:id_job]/[:password]/options', 'POST', 'API\V2\ChunkOptionsController', 'update' ); 

$klein->with('/api/v2/jobs/[:id_job]/[:password]', function() {
    
    route( '/comments',     'GET', 'API\V2\CommentsController', 'index' );

    /**
     * This should be moved in plugin space
     */
    route( '/quality-report', 'GET',
       'Features\ReviewImproved\Controller\API\QualityReportController', 'show'
    );
});
route(
    '/webhooks/gdrive/open', 'GET', 
    'GDriveController', 'open'
); 
route(
    '/gdrive/list', 'GET',
    'GDriveController', 'listImportedFiles'
); 
route(
    '/gdrive/change/[:sourceLanguage]', 'GET',
    'GDriveController', 'changeSourceLanguage'
); 
route(
    '/gdrive/delete/[:fileId]', 'GET',
    'GDriveController', 'deleteImportedFile'
);
route(
    '/gdrive/verify', 'GET',
    'GDriveController', 'isGDriveAccessible'
);

$klein->dispatch();
