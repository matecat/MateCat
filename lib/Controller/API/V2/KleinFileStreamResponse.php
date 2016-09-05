<?php
/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 01/09/16
 * Time: 12:15
 */

namespace API\V2;


use FilesStorage;
use Klein\DataCollection\HeaderDataCollection;
use Klein\Response;
use Klein\DataCollection\ResponseCookieDataCollection;

class KleinFileStreamResponse extends Response {

    /**
     * Sends a file streaming from a file pointer resource
     *
     * It should be noted that this method disables caching
     * of the response by default, as dynamically created
     * files responses are usually downloads of some type
     * and rarely make sense to be HTTP cached
     *
     * Also, this method removes any data/content that is
     * currently in the response body and replaces it with
     * the file's data
     *
     * @param resource $filePointer      The pointer to the file to send
     * @param string $filename  The file's name
     * @param KleinController $controller  The MIME type of the file
     * @return Response
     */
    public function file( $filePointer, $filename = null, $controller = null )
    {

        if ( !empty( $controller ) && method_exists( $controller, 'unlockToken' )  ){
            $controller->unlockToken();
        }

        $this->body('');
        $this->noCache();

        if ( null !== $filename ) {
            $filename = FilesStorage::basename_fix( $filename );
        }

        $this->header('Content-type', "application/download" );
        $this->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $this->header('Expires', "0" );
        $this->header('Connection', "close" );

        $this->send();

        while ( !feof( $filePointer ) ) {
            echo fgets( $filePointer, 2048 );
        }

        fclose( $filePointer );

        return $this;
    }

    /**
     * @param ResponseCookieDataCollection $cookies
     *
     * @return ResponseCookieDataCollection
     */
    public function cookie( ResponseCookieDataCollection $cookies = null ){

        if( !empty( $cookies ) ){
            $this->cookies = $cookies;
        }

        return $this->cookies;
    }

    /**
     * @param HeaderDataCollection|null $headers
     *
     * @return HeaderDataCollection
     */
    public function headers( HeaderDataCollection $headers = null ){

        if( !empty( $headers ) ){
            $this->headers = $headers;
        }

        return $this->headers;

    }

}