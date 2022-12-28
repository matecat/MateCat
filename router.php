<?php

use API\V2\Exceptions\AuthenticationError;
use API\V2\Exceptions\AuthorizationError;
use API\V2\Exceptions\ExternalServiceException;
use API\V2\Exceptions\UnprocessableException;
use API\V2\Exceptions\ValidationError;
use API\V2\Json\Error;
use Exceptions\NotFoundException;
use Exceptions\ValidationError as Model_ValidationError;
use Klein\Klein;

require_once './inc/Bootstrap.php';

Bootstrap::start();

$klein = new Klein();

function route( $path, $method, $controller, $action ) {
    global $klein;

    $klein->respond( $method, $path, function () use ( $controller, $action ) {
        $reflect  = new ReflectionClass( $controller );
        $instance = $reflect->newInstanceArgs( func_get_args() );
        $instance->respond( $action );
    } );
}

$klein->onError( function ( Klein $klein, $err_msg, $err_type, Exception $exception ) {
    // TODO: still need to catch fatal errors here with 500 code
    $klein->response()->noCache();

    try {
        throw $exception;
    }  catch( InvalidArgumentException $e ){
        $klein->response()->code( 400 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( AuthenticationError $e ) {
        $klein->response()->code( 401 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( Model_ValidationError $e ) {
        $klein->response()->code( 400 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( ValidationError $e ) {
        $klein->response()->code( 400 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( AuthorizationError $e ) {
        $klein->response()->code( 403 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( DomainException $e ) {
        $klein->response()->code( 403 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( NotFoundException $e ) {
        Log::doJsonLog( 'Record Not found error for URI: ' . $_SERVER[ 'REQUEST_URI' ] );
        $klein->response()->code( 404 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch( UnprocessableException $e ){
        $klein->response()->code( 422 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( ExternalServiceException $e ) {
        $klein->response()->code( 503 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( PDOException $e ) {
        $klein->response()->code( 503 );
//        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
        Utils::sendErrMailReport( $exception->getMessage() . "" . $exception->getTraceAsString(), 'Generic error' );
        Log::doJsonLog( [ "error" => $exception->getMessage(), "trace" => $exception->getTrace() ] );
    } catch ( Exception $e ){
        $klein->response()->code( 500 );
        Utils::sendErrMailReport( $exception->getMessage() . "" . $exception->getTraceAsString(), 'Generic error' );
        Log::doJsonLog( [ "error" => $exception->getMessage(), "trace" => $exception->getTrace() ] );
    }

} );

require './lib/Routes/api_v1_routes.php';
require './lib/Routes/api_v2_routes.php';
require './lib/Routes/api_v3_routes.php';
require './lib/Routes/gdrive_routes.php';
require './lib/Routes/utils_routes.php';
Features::loadRoutes( $klein );

$klein->dispatch();
