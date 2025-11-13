<?php
/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 01/09/16
 * Time: 12:15
 */

namespace Controller\Traits;


use Klein\Response;
use Model\FilesStorage\AbstractFilesStorage;

class KleinResponseFileStream
{

    /**
     * @var Response
     */
    protected Response $response;

    /**
     * KleinResponseFileStream constructor.
     *
     * @param Response $response
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    /**
     * Sends a file streaming from a file pointer resource
     *
     * It should be noted that this method disables caching
     * of the response by default, as dynamically created
     * files responses are usually downloads of some type
     * and rarely make sense to be HTTP cached
     *
     * Also, this method removes any data/content
     * currently in the response body and replaces it with
     * the file's data
     *
     * @param resource $filePointer The pointer to the file to send
     * @param string   $filename    The file's name
     * @param string   $mimeType
     * @param string   $disposition
     *
     * @internal param KleinController $controller The MIME type of the file
     */
    public function streamFileFromPointer($filePointer, string $mimeType, string $disposition, string $filename): void
    {
        $this->response->body('');
        $this->response->noCache();

        $filename = AbstractFilesStorage::basename_fix($filename);

        $this->response->header('Content-type', $mimeType);
        $this->response->header('Content-Disposition', $disposition . '; filename="' . $filename . '"');
        $this->response->header('Expires', "0");
        $this->response->header('Connection', "close");

        $this->response->send();

        while (!feof($filePointer)) {
            echo fgets($filePointer, 2048);
        }

        fclose($filePointer);
    }

    public function streamFileDownloadFromPointer($filePointer, $filename)
    {
        $this->streamFileFromPointer($filePointer, "application/download", 'attachment', $filename);
    }

    public function streamFileInlineFromPointer($filePointer, $filename, $mimeType)
    {
        $this->streamFileFromPointer($filePointer, $mimeType, 'inline', $filename);
    }

}