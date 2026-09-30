<?php

use Controller\Cors\CorsHandler;
use Utils\Registry\AppConfig;

error_reporting(E_ALL | E_STRICT);

ini_set('max_input_time', 3600);

require_once realpath(dirname(__FILE__) . '/../../../') . '/lib/Bootstrap.php';

/** @noinspection PhpUnhandledExceptionInspection */
Bootstrap::start();

require_once(realpath('./UploadHandler.php'));

$upload_handler = new UploadHandler(Bootstrap::getDatabase(), $_FILES);

header('Pragma: no-cache');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Disposition: inline; filename="files.json"');
header('X-Content-Type-Options: nosniff');
// Reflect ONLY this instance's own app origin (credentialed), never a wildcard:
// `*` cannot carry credentials and trusts every origin (CWE-942).
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$cors = new CorsHandler(AppConfig::$HTTPHOST, AppConfig::$ENABLE_MULTI_DOMAIN_API);
if ($cors->isAllowed($origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
    // Upload-specific verbs/headers (HEAD + X-File-*):
    header('Access-Control-Allow-Methods: OPTIONS, HEAD, GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: X-File-Name, X-File-Type, X-File-Size');
}

try {
    switch ($_SERVER['REQUEST_METHOD'] ?? '') {
        case 'OPTIONS':
            break;
        case 'HEAD':
        case 'GET':
        // Answers "what is already in my upload folder?". The blueimp widget asked on page load
        // and replayed the answer as finished rows; the React uploader that replaced it calls
        // this and discards the body (UploadFile.js), so nothing renders from it today.
        //
        // A branch here used to return an empty list instead when the session held Google Drive
        // files, so the widget would not replay rows the Drive flow was rendering itself. It
        // read the session, which this page stopped opening in July 2025, so it had been
        // returning the plain listing ever since with nobody reading either answer.
        $upload_handler->get();
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