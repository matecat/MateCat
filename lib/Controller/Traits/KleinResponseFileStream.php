<?php
/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 01/09/16
 * Time: 12:15
 */

namespace Controller\Traits;


use Controller\Abstracts\AbstractDownloadController;
use Klein\Response;

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
     * Sends the file with the headers of every download: the cache headers of noCache(), then the
     * type, name and size of the file. Each one replaces a header of the same name already set,
     * such as those session_start() adds, so a header is never sent twice.
     *
     * @param resource $filePointer
     */
    public function streamFileFromPointer($filePointer, string $mimeType, string $disposition, string $filename): void
    {
        try {
            $this->noCache();

            $filename = AbstractDownloadController::sanitizeContentDispositionFilename($filename);

            $this->header('Content-Type: ' . $mimeType);
            $this->header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
            $this->header('Expires: 0');
            $this->header('Connection: close');

            $stat = fstat($filePointer);
            if ($stat !== false) {
                $this->header('Content-Length: ' . $stat['size']);
            }

            // Copies the stream to the output in chunks, so a large file is never held in memory.
            // Not Response::send(): under PHP-FPM it ends the request with fastcgi_finish_request(),
            // and the bytes written after it never reach the client.
            fpassthru($filePointer);

            $this->response->lock();
        } finally {
            fclose($filePointer);
        }
    }

    /**
     * Tells the browser and every proxy not to keep a copy of the response.
     */
    public function noCache(): void
    {
        $this->header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        $this->header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        $this->header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        $this->header("Cache-Control: post-check=0, pre-check=0", false);
        $this->header("Pragma: no-cache");
    }

    /**
     * Sends one header with PHP's header(). A method so a test can see what is sent.
     * Example: `header('Expires: 0')` replaces an `Expires` sent before; with `$replace` false
     * both are sent.
     */
    protected function header(string $header, bool $replace = true): void
    {
        header($header, $replace);
    }

    /**
     * @param resource $filePointer
     */
    public function streamFileDownloadFromPointer($filePointer, string $filename): void
    {
        $this->streamFileFromPointer($filePointer, "application/download", 'attachment', $filename);
    }

    /**
     * @param resource $filePointer
     */
    public function streamFileInlineFromPointer($filePointer, string $filename, string $mimeType): void
    {
        $this->streamFileFromPointer($filePointer, $mimeType, 'inline', $filename);
    }

}