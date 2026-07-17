<?php

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Exceptions\ConflictError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Exceptions\UnprocessableException;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\Cors\CorsHandler;
use Klein\App;
use Klein\Klein;
use Klein\Request;
use Klein\Response;
use Matecat\Locales\InvalidLanguageException;
use Model\Exceptions\ValidationError as Model_ValidationError;
use Model\FeaturesBase\PluginsLoader;
use Swaggest\JsonSchema\InvalidValue;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use View\API\Commons\Error;

require_once './lib/Bootstrap.php';

/** @noinspection PhpUnhandledExceptionInspection */
Bootstrap::start();

$app = new App();
$app->register('getDatabase', fn() => Bootstrap::getDatabase());
$klein = new Klein(app: $app);
$isView = false;

// Handler #1 of 2 — STAMP the CORS headers on EVERY response.
// `null` method + `'*'` path matches every request (GET/POST/OPTIONS, even a
// 404), because a normal GET/POST response also needs the headers or the
// browser won't let the shard page read it. It does NOT short-circuit — the
// matched controller still runs afterwards. Registered FIRST so it runs before
// any controller send()s and locks the Response (klein >= 3.3.1 dispatches
// matched routes in registration order; older forks ran catch-alls last).
$klein->respond(null, '*', function (Request $request, Response $response): void {
    // CORS for the AJAX domain-sharding feature. The page is served from
    // AppConfig::$HTTPHOST and its XHR calls target the shard hosts
    // ({i}.ajax.<host>), a different origin, so those requests carry
    // `Origin: <HTTPHOST>` and must be allowed. The handler reflects ONLY that one
    // origin — never a wildcard or sibling subdomain (CWE-942) — derived from
    // config so it is correct on any install domain.
    (new CorsHandler(
        AppConfig::$HTTPHOST,
        AppConfig::$ENABLE_MULTI_DOMAIN_API
    ))->apply($request, $response);
});

// Handler #2 of 2 — ANSWER the CORS preflight.
// A preflight is an OPTIONS request; no controller declares OPTIONS, so without
// this it would fall through to 405. This registers OPTIONS as handled and
// returns the correct empty 204. Runs together with handler #1, so a preflight
// gets `204` + the Access-Control-Allow-* headers. Kept separate from #1
// because the 204 must apply ONLY to OPTIONS, while the header stamping applies
// to every method.
$klein->respond('OPTIONS', '*', function (Request $request, Response $response): void {
    $response->code(204);
});

/**
 * @param string $path
 * @param string $method
 * @param array{0: class-string<KleinController>, 1: string} $callback
 *
 * @return void
 */
function route(string $path, string $method, array $callback): void
{
    global $klein, $isView;
    $klein->respond($method, $path, function () use ($callback, &$isView) {
        $reflect = new ReflectionClass($callback[0]);
        /** @var KleinController $instance */
        $instance = $reflect->newInstanceArgs(func_get_args());
        $isView = $instance->isView();
        $instance->respond($callback[1]);
    });
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
$klein->onHttpError(function (int $code, Klein $klein) use (&$isView) {
    /** @var bool $isView */
    // Check if the error code is 404 (page not found)
    if ($code == 404) {
        if ($isView) {
            throw new NotFoundException('Not Found.'); // This will be caught by the Bootstrap exception handler
        } else {
            // If not a view, return a JSON response with the error
            $klein->response()->code(404);
            $klein->response()->json((new Error(new NotFoundException('Not Found.')))->render());
        }
    }
});

$klein->onError(function (Klein $klein, string $err_msg, string $err_type, Throwable $exception) use (&$isView) {
    if (!$isView) {
        $klein->response()->noCache();
        $logger = LoggerFactory::getLogger('exception_handler', 'fatal_errors.txt');

        switch (get_class($exception)) {
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
                $klein->response()->code(400);
                $klein->response()->json((new Error($exception))->render());
                break;
            case AuthenticationError::class: // authentication requested
                $klein->response()->code(401);
                $klein->response()->json((new Error($exception))->render());
                break;
            case AuthorizationError::class: // invalid permissions to access the resource
            case \Model\Exceptions\AuthorizationError::class:
                $klein->response()->code(403);
                $klein->response()->json((new Error($exception))->render());
                break;
            case NotFoundException::class:
            case Model\Exceptions\NotFoundException::class:
            $logger->debug('Record Not found error for URI: ' . $_SERVER['REQUEST_URI']);
                $logger->debug((new Error($exception))->render(true));
                $klein->response()->code(404);
                $klein->response()->json((new Error($exception))->render());
                break;
            case ConflictError::class:
                $logger->debug('Conflict error for URI: ' . $_SERVER['REQUEST_URI']);
                $logger->debug((new Error($exception))->render(true));
                $klein->response()->code(409);
                $klein->response()->json((new Error($exception))->render());
                break;
            case UnprocessableException::class:
                $klein->response()->code(422);
                $klein->response()->json((new Error($exception))->render());
                break;
            case PDOException::class:
                $klein->response()->code(503);
                $logger->debug((new Error($exception))->render(true));
                break;
            default:
                $httpCode = $exception->getCode() >= 400 && $exception->getCode() <= 511 ? $exception->getCode() : 500;
                $klein->response()->code($httpCode);
                $logger->debug((new Error($exception))->render(true));
                $klein->response()->json(new Error($exception));
                break;
        }
    } else {
        // if the error is in a view, we must render the exception through the Bootstrap exception handler
        throw $exception;
    }
});

require './lib/Routes/view_routes.php';
require './lib/Routes/api_v1_routes.php';
require './lib/Routes/api_v2_routes.php';
require './lib/Routes/api_v3_routes.php';
require './lib/Routes/gdrive_routes.php';
require './lib/Routes/oauth_routes.php';
require './lib/Routes/app_routes.php';
PluginsLoader::loadRoutes($klein);

/** @noinspection PhpUnhandledExceptionInspection */
$klein->dispatch();
