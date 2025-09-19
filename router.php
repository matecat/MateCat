<?php

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Exceptions\UnprocessableException;
use Controller\API\Commons\Exceptions\ValidationError;
use Klein\Klein;
use Model\Exceptions\ValidationError as Model_ValidationError;
use Model\FeaturesBase\PluginsLoader;
use Swaggest\JsonSchema\InvalidValue;
use Utils\Langs\InvalidLanguageException;
use Utils\Logger\LoggerFactory;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use View\API\Commons\Error;

require_once './lib/Bootstrap.php';

/** @noinspection PhpUnhandledExceptionInspection */
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
        $isView   = $instance->isView();
        $instance->respond( $callback[ 1 ] );
    } );
}

/**
 * Handles HTTP errors for the Klein router.
 * Since the Klein router now manages all requests (see .htaccess),
 * this function is triggered whenever an HTTP error occurs.
 *
 * Specifically, it handles 404 errors (when no routes match in Klein)
 * by rendering a custom view for the 404 error page.
 *
 * @param int $code The HTTP error code.
 */
$klein->onHttpError( function ( int $code, Klein $klein ) use ( &$isView ) {
    // Check if the error code is 404 (page not found)
    if ( $code == 404 ) {
        if ( $isView ) {
            throw new NotFoundException( 'Not Found.' ); // This will be caught by the Bootstrap exception handler
        } else {
            // If not a view, return a JSON response with the error
            $klein->response()->code( 404 );
            $klein->response()->json( ( new Error( new NotFoundException( 'Not Found.' ) ) )->render() );
        }
    }
} );

$klein->onError( function ( Klein $klein, $err_msg, $err_type, Throwable $exception ) use ( &$isView ) {

    if ( !$isView ) {

        $klein->response()->noCache();
        $logger = LoggerFactory::getLogger( 'exception_handler', 'fatal_errors.txt' );

        switch ( get_class( $exception ) ) {
            case \Swaggest\JsonSchema\Exception::class:
            case JSONValidatorException::class:
            case JsonValidatorGenericException::class:
            case ValidationError::class:
            case InvalidLanguageException::class:
            case InvalidValue::class:
            case InvalidArgumentException::class:
            case DomainException::class:
            case UnexpectedValueException::class:
            case Model_ValidationError::class:
                $klein->response()->code( 400 );
                $klein->response()->json( ( new Error( $exception ) )->render() );
                break;
            case AuthenticationError::class: // authentication requested
                $klein->response()->code( 401 );
                $klein->response()->json( ( new Error( $exception ) )->render() );
                break;
            case AuthorizationError::class: // invalid permissions to access the resource
            case \Model\Exceptions\AuthorizationError::class:
                $klein->response()->code( 403 );
                $klein->response()->json( ( new Error( $exception ) )->render() );
                break;
            case NotFoundException::class:
            case Model\Exceptions\NotFoundException::class:
                $logger->debug( 'Record Not found error for URI: ' . $_SERVER[ 'REQUEST_URI' ] );
                $logger->debug( ( new Error( $exception ) )->render( true ) );
                $klein->response()->code( 404 );
                $klein->response()->json( ( new Error( $exception ) )->render() );
                break;
            case UnprocessableException::class:
                $klein->response()->code( 422 );
                $klein->response()->json( ( new Error( $exception ) )->render() );
                break;
            case PDOException::class:
                $klein->response()->code( 503 );
                $logger->debug( ( new Error( $exception ) )->render( true ) );
                break;
            default:
                $httpCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
                $klein->response()->code( $httpCode );
                $logger->debug(( new Error( $exception ) )->render( true ) );
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
PluginsLoader::loadRoutes( $klein );

$klein->dispatch();
