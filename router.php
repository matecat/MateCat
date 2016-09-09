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

require './lib/Routes/api_v1_routes.php';
require './lib/Routes/api_v2_routes.php';
require './lib/Routes/gdrive_routes.php';
require './lib/Routes/utils_routes.php';

$klein->dispatch();
