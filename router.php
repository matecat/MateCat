<?php

use AbstractControllers\BaseKleinViewController;
use AbstractControllers\KleinController;
use API\Commons\Error;
use API\Commons\Exceptions\AuthenticationError;
use API\Commons\Exceptions\AuthorizationError;
use API\Commons\Exceptions\NotFoundException;
use API\Commons\Exceptions\UnprocessableException;
use API\Commons\Exceptions\ValidationError;
use Exceptions\ValidationError as Model_ValidationError;
use Klein\Klein;
use Langs\InvalidLanguageException;
use Swaggest\JsonSchema\InvalidValue;
use Validator\Errors\JSONValidatorException;
use Validator\Errors\JsonValidatorGenericException;

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

        switch ( get_class( $exception ) ) {
            case \Swaggest\JsonSchema\Exception::class:
            case JSONValidatorException::class:
            case JsonValidatorGenericException::class:
            case ValidationError::class:
            case InvalidLanguageException::class:
            case InvalidValue::class:
            case InvalidArgumentException::class:
            case DomainException::class:
            case Model_ValidationError::class:
                $klein->response()->code( 400 );
                $klein->response()->json( ( new Error( $exception ) )->render() );
                break;
            case AuthenticationError::class: // authentication requested
                $klein->response()->code( 401 );
                $klein->response()->json( ( new Error( $exception ) )->render() );
                break;
            case AuthorizationError::class: // invalid permissions to access the resource
                $klein->response()->code( 403 );
                $klein->response()->json( ( new Error( $exception ) )->render() );
                break;
            case NotFoundException::class:
            case Exceptions\NotFoundException::class:
                Log::doJsonLog( 'Record Not found error for URI: ' . $_SERVER[ 'REQUEST_URI' ] );
                Log::doJsonLog( json_encode( ( new Error( $exception ) )->render( true ) ) );
                $klein->response()->code( 404 );
                $klein->response()->json( ( new Error( $exception ) )->render() );
                break;
            case UnprocessableException::class:
                $klein->response()->code( 422 );
                $klein->response()->json( ( new Error( $exception ) )->render() );
                break;
            case PDOException::class:
                $klein->response()->code( 503 );
                Log::doJsonLog( json_encode( ( new Error( $exception ) )->render( true ) ) );
                break;
            default:
                $httpCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
                $klein->response()->code( $httpCode );
                Log::doJsonLog( json_encode( ( new Error( $exception ) )->render( true ) ) );
                $klein->response()->json( new Error( $exception ) );
                break;
        }

    } else {
        // if the error is in a view, we must render the exception through the Bootstrap exception handler
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
