<?php
/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 01/09/16
 * Time: 12:15
 */

namespace Controller\Traits;


use Klein\Exceptions\ResponseAlreadySentException;
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
     * @param resource $filePointer
     *
     * @throws ResponseAlreadySentException
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

    /**
     * @param resource $filePointer
     *
     * @throws ResponseAlreadySentException
     */
    public function streamFileDownloadFromPointer($filePointer, string $filename): void
    {
        $this->streamFileFromPointer($filePointer, "application/download", 'attachment', $filename);
    }

    /**
     * @param resource $filePointer
     *
     * @throws ResponseAlreadySentException
     */
    public function streamFileInlineFromPointer($filePointer, string $filename, string $mimeType): void
    {
        $this->streamFileFromPointer($filePointer, $mimeType, 'inline', $filename);
    }

}