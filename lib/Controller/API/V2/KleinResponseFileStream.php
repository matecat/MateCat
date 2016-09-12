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
use Klein\Klein;
use Klein\Response;
use Klein\DataCollection\ResponseCookieDataCollection;

class KleinResponseFileStream  {

    private $response ;

    public function __construct(Response $response )
    {
        $this->response = $response ;
    }

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
     */
    public function streamFileFromPointer( $filePointer, $filename = null )
    {

        $this->response->body('');
        $this->response->noCache();

        if ( null !== $filename ) {
            $filename = FilesStorage::basename_fix( $filename );
        }

        $this->response->header('Content-type', "application/download" );
        $this->response->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $this->response->header('Expires', "0" );
        $this->response->header('Connection', "close" );

        $this->response->send();

        while ( !feof( $filePointer ) ) {
            echo fgets( $filePointer, 2048 );
        }

        fclose( $filePointer );
    }

}