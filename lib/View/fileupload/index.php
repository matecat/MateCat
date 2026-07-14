<?php

use Controller\Cors\CorsHandler;
use Model\ConnectedServices\GDrive\Session;
use Utils\Registry\AppConfig;

error_reporting(E_ALL | E_STRICT);

ini_set('max_input_time', 3600);

require_once realpath(dirname(__FILE__) . '/../../../') . '/lib/Bootstrap.php';

/** @noinspection PhpUnhandledExceptionInspection */
Bootstrap::start();

require_once('UploadHandler.php');

$upload_handler = new UploadHandler(Bootstrap::getDatabase(), $_FILES);

header('Pragma: no-cache');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Disposition: inline; filename="files.json"');
header('X-Content-Type-Options: nosniff');
// Reflect ONLY this instance's own app origin (credentialed), never a wildcard:
// `*` cannot carry credentials and trusts every origin (CWE-942).
$cors = new CorsHandler(AppConfig::$HTTPHOST, AppConfig::$ENABLE_MULTI_DOMAIN_API);
foreach ($cors->responseHeaders($_SERVER['HTTP_ORIGIN'] ?? '') as $corsName => $corsValue) {
    header($corsName . ': ' . $corsValue);
}
header('Access-Control-Allow-Methods: OPTIONS, HEAD, GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: X-File-Name, X-File-Type, X-File-Size');

try {
    switch ($_SERVER['REQUEST_METHOD'] ?? '') {
        case 'OPTIONS':
            break;
        case 'HEAD':
        case 'GET':
            if (!(new Session(Bootstrap::getDatabase()))->sessionHasFiles()) {
                $upload_handler->get();
            } else {
                echo json_encode([]);
            }
            break;
        case 'POST':
            if (isset($_REQUEST['_method']) && $_REQUEST['_method'] === 'DELETE') {
                $upload_handler->delete();
            } else {
                $upload_handler->post();
            }
            break;
        case 'DELETE':
            $upload_handler->delete();
            break;
        default:
            header('HTTP/1.1 405 Method Not Allowed');
    }
} catch (Throwable $e) {
    header('HTTP/1.1 400 Bad Request');
}