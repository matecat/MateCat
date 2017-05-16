<?php

use API\V2\Exceptions\NotFoundException;
use API\V2\Json\Error;
use API\V2\Exceptions\AuthenticationError;
use API\V2\Exceptions\AuthorizationError;
use API\V2\Exceptions\ValidationError;
use Exceptions\NotFoundError;
use Exceptions\ValidationError as Model_ValidationError;

require_once './inc/Bootstrap.php';
require_once './lib/Model/queries.php';

Bootstrap::start();

$klein = new \Klein\Klein();

function route( $path, $method, $controller, $action ) {
    global $klein;

    $klein->respond( $method, $path, function () use ( $controller, $action ) {
        $reflect  = new ReflectionClass( $controller );
        $instance = $reflect->newInstanceArgs( func_get_args() );
        $instance->respond( $action );
    } );
}

Features::loadRoutes( $klein );

$klein->onError( function ( \Klein\Klein $klein, $err_msg, $err_type, Exception $exception ) {
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
    } catch ( DomainException $e ){
        $klein->response()->code( 403 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( Exceptions_RecordNotFound $e ) {
        \Log::doLog( 'Record Not found error for URI: ' . $_SERVER[ 'REQUEST_URI' ] );
        $klein->response()->code( 404 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( NotFoundException $e ) {
        \Log::doLog( 'Record Not found error for URI: ' . $_SERVER[ 'REQUEST_URI' ] );
        $klein->response()->code( 404 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( NotFoundError $e ){
        \Log::doLog( 'Not found error for URI: ' . $_SERVER[ 'REQUEST_URI' ] );
        $klein->response()->code( 404 );
        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
    } catch ( \PDOException $e ) {
        $klein->response()->code( 503 );
//        $klein->response()->json( ( new Error( [ $e ] ) )->render() );
        \Utils::sendErrMailReport( $exception->getMessage() . "" . $exception->getTraceAsString(), 'Generic error' );
        \Log::doLog( "Error: {$exception->getMessage()} " );
        \Log::doLog( $exception->getTraceAsString() );
    } catch ( Exception $e ){
        $klein->response()->code( 500 );
        \Utils::sendErrMailReport( $exception->getMessage() . "" . $exception->getTraceAsString(), 'Generic error' );
        \Log::doLog( "Error: {$exception->getMessage()} " );
        \Log::doLog( $exception->getTraceAsString() );
    }

} );

require './lib/Routes/api_v1_routes.php';
require './lib/Routes/api_v2_routes.php';
require './lib/Routes/gdrive_routes.php';
require './lib/Routes/utils_routes.php';

$klein->dispatch();
