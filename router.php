<?php

use AbstractControllers\BaseKleinViewController;
use AbstractControllers\KleinController;
use API\Commons\Error;
use API\Commons\Exceptions\AuthenticationError;
use API\Commons\Exceptions\AuthorizationError;
use API\Commons\Exceptions\ExternalServiceException;
use API\Commons\Exceptions\NotFoundException;
use API\Commons\Exceptions\UnprocessableException;
use API\Commons\Exceptions\ValidationError;
use Exceptions\ValidationError as Model_ValidationError;
use Klein\Klein;

require_once './inc/Bootstrap.php';

Bootstrap::start();

$klein  = new Klein();
$isView = false;

/**
 * @param string $path
 * @param string $method
 * @param array  $callback
 *
 * @return void
 */
function route( string $path, string $method, array $callback ) {
    global $klein, $isView;
    $klein->respond( $method, $path, function () use ( $callback, &$isView ) {
        $reflect = new ReflectionClass( $callback[ 0 ] );
        /** @var KleinController $instance */
        $instance = $reflect->newInstanceArgs( func_get_args() );
        $isView   = $instance instanceof BaseKleinViewController;
        $instance->respond( $callback[ 1 ] );
    } );
}

$klein->onError( function ( Klein $klein, $err_msg, $err_type, Throwable $exception ) use ( &$isView ) {

    if ( !$isView ) {

        $klein->response()->noCache();
        Log::$fileName = 'fatal_errors.txt';

        try {
            throw $exception;
        } catch ( ValidationError|InvalidArgumentException|Model_ValidationError $e ) {
            $klein->response()->code( 400 );
            $klein->response()->json( ( new Error( [ $e ] ) )->render() );
        } catch ( AuthenticationError $e ) {
            $klein->response()->code( 401 );
            $klein->response()->json( ( new Error( [ $e ] ) )->render() );
        } catch ( AuthorizationError|DomainException $e ) {
            $klein->response()->code( 403 );
            $klein->response()->json( ( new Error( [ $e ] ) )->render() );
        } catch ( NotFoundException|Exceptions\NotFoundException $e ) {
            Log::doJsonLog( 'Record Not found error for URI: ' . $_SERVER[ 'REQUEST_URI' ] );
            $klein->response()->code( 404 );
            $klein->response()->json( ( new Error( [ $e ] ) )->render() );
        } catch ( UnprocessableException $e ) {
            $klein->response()->code( 422 );
            $klein->response()->json( ( new Error( [ $e ] ) )->render() );
        } catch ( ExternalServiceException $e ) {
            $klein->response()->code( 503 );
            $klein->response()->json( ( new Error( [ $e ] ) )->render() );
        } catch ( PDOException $e ) {
            $klein->response()->code( 503 );
            Log::doJsonLog( [ "error" => $exception->getMessage(), "trace" => $exception->getTrace() ] );
        } catch ( Throwable $e ) {
            $klein->response()->code( 500 );
            Log::doJsonLog( [ "error" => $exception->getMessage(), "trace" => $exception->getTrace() ] );
            $klein->response()->json( ( new Error( [ $e ] ) )->render() );
        }

    } else {
        // if the error is in a view, we need to render the exception through the Bootstrap exception handler
        throw $exception;
    }

} );

require './lib/Routes/view_routes.php';
require './lib/Routes/api_v1_routes.php';
require './lib/Routes/api_v2_routes.php';
require './lib/Routes/api_v3_routes.php';
require './lib/Routes/gdrive_routes.php';
require './lib/Routes/oauth_routes.php';
require './lib/Routes/app_routes.php';
Features::loadRoutes( $klein );

$klein->dispatch();
