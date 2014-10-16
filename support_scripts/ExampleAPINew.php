<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/05/14
 * Time: 19.28
 *
 */

/*
 *
 * The post with curl will produce this output on the Matecat Side
 *
 *   //var_export( $POST );
 *   $_POST = array (
 *     'project_name' => 'MyProject',
 *     'source_lang' => 'en-US',
 *     'target_lang' => 'fr-FR',
 *     'action' => 'New',
 *   );
 *
 *   //var_export( $_FILES );
 *   $_FILES = array (
 *     'fileUpload' =>
 *     array (
 *       'name' => 'File_001.odt.sdlxliff',
 *       'type' => 'application/octet-stream',
 *       'tmp_name' => '/tmp/phpVzKBIM',
 *       'error' => 0,
 *       'size' => 157380,
 *     ),
 *     'fileUpload1' =>
 *     array (
 *       'name' => 'File_02.doc.sdlxliff',
 *       'type' => 'application/octet-stream',
 *       'tmp_name' => '/tmp/phpSe1fvB',
 *       'error' => 0,
 *       'size' => 1198013,
 *     ),
 *   );
 *
*/

/**
 * Array o files with absolute path
 * @var $files array
 */
$files = array( '/home/myUser/Documents/File_001.odt.sdlxliff', '/home/myUser/Documents/File_02.doc.sdlxliff' );

/**
 * Configure your matecat Url
 *
 * @var $url string
 */
$url = 'http://matecat.local/api/new';

/*******************************/


$data = array();

$n = sizeof( $files );

$data[ 'project_name' ] = 'MyProject';
$data[ 'source_lang' ]  = 'en-US';
$data[ 'target_lang' ]  = 'fr-FR';

for ( $i = 0; $i < $n; $i++ ) {
    $data[ 'fileUpload' . $i ] = "@" . $files[ $i ];
}

$ch = curl_init();
curl_setopt( $ch, CURLOPT_URL, $url );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
curl_setopt( $ch, CURLOPT_POST, true );
curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
$response = curl_exec( $ch );

var_export( $response );
//{"status":"OK","message":"Success","id_project":"5655","project_pass":"8b07f5ce98ce"}