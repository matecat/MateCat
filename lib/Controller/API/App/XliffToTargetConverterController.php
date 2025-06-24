<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/05/25
 * Time: 19:18
 *
 */

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Conversion\Filters;
use Conversion\MimeTypes\MimeTypes;

set_time_limit( 180 );

class XliffToTargetConverterController extends KleinController {

    public function convert() {

        $file_path = $_FILES[ 'xliff' ][ 'tmp_name' ] . '.xlf';
        move_uploaded_file( $_FILES[ 'xliff' ][ 'tmp_name' ], $file_path );

        $conversion = Filters::xliffToTarget( [
                [
                        'document_content' => file_get_contents( $file_path )
                ]
        ] );
        $conversion = $conversion[ 0 ];

        $error         = false;
        $errorMessage  = null;
        $outputContent = null;
        $filename      = '';

        if ( $conversion[ 'successful' ] === true ) {
            $outputContent = json_encode( [
                    "fileName"    => ( $conversion[ 'fileName' ] ?? $conversion[ 'filename' ] ),
                    "fileContent" => base64_encode( $conversion[ 'document_content' ] ),
                    "size"        => filesize( $file_path ),
                    "type"        => ( new MimeTypes() )->guessMimeType( $file_path ),
                    "message"     => "File downloaded! Check your download folder"
            ] );
            $filename      = $conversion[ 'fileName' ];
        } else {
            $error        = true;
            $errorMessage = $conversion[ 'errorMessage' ];
        }

        if ( $error ) {

            $this->response->code( 500 );

            if ( empty( $errorMessage ) ) {
                $this->response->body( "(No error message provided)" );
            } else {
                $this->response->body( $errorMessage );
            }

        } else {

            $this->response->body( $outputContent );
            $this->response->header( "Content-Type", "application/force-download" );
            $this->response->header( "Content-Type", "application/octet-stream" );
            $this->response->header( "Content-Type", "application/download" );
            $this->response->header( 'Content-Disposition', 'attachment; filename="' . $filename . '"' ); // enclose file name in double quotes in order to avoid duplicate header error. Reference https://github.com/prior/prawnto/pull/16
            $this->response->header( 'Content-Transfer-Encoding', 'binary' );
            $this->response->header( 'Expires', "0" );
            $this->response->header( 'Connection', "close" );

        }

        $this->response->send();

    }

}