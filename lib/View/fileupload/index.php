<?php

use ConnectedServices\Google\GDrive\Session;

error_reporting( E_ALL | E_STRICT );

ini_set( 'max_input_time', 3600 );

require_once realpath( dirname( __FILE__ ) . '/../../../' ) . '/inc/Bootstrap.php';
@Bootstrap::start();
@Bootstrap::sessionStart();
@Bootstrap::sessionClose();
require_once( 'upload.class.php' );

$upload_handler = new UploadHandler();

header( 'Pragma: no-cache' );
header( 'Cache-Control: no-store, no-cache, must-revalidate' );
header( 'Content-Disposition: inline; filename="files.json"' );
header( 'X-Content-Type-Options: nosniff' );
header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Methods: OPTIONS, HEAD, GET, POST, PUT, DELETE' );
header( 'Access-Control-Allow-Headers: X-File-Name, X-File-Type, X-File-Size' );

switch ( $_SERVER[ 'REQUEST_METHOD' ] ) {
    case 'OPTIONS':
        break;
    case 'HEAD':
    case 'GET':
        if ( !( new Session() )->sessionHasFiles() ) {
            $upload_handler->get();
        } else {
            echo json_encode( [] );
        }
        break;
    case 'POST':
        if ( isset( $_REQUEST[ '_method' ] ) && $_REQUEST[ '_method' ] === 'DELETE' ) {
            $upload_handler->delete();
        } else {
            $upload_handler->post();
        }
        break;
    case 'DELETE':
        $upload_handler->delete();
        break;
    default:
        header( 'HTTP/1.1 405 Method Not Allowed' );
}

