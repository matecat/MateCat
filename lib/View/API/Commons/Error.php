<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/02/17
 * Time: 16.39
 *
 */

namespace View\API\Commons;


use JsonSerializable;
use Throwable;
use Utils\Registry\AppConfig;

class Error implements JsonSerializable
{

    private Throwable $data;

    /**
     * Error constructor.
     *
     * @param Throwable $exceptions
     */
    public function __construct(Throwable $exceptions)
    {
        $this->data = $exceptions;
    }

    public function render(bool $force_print_errors = false): array
    {
        $row = [
                "errors" => [],
                "data"   => []
        ];

        $code   = $this->data->getCode();
        $output = $this->data->getMessage();

        if (AppConfig::$PRINT_ERRORS || $force_print_errors) {
            $row[ 'errors' ][ 0 ][ 'file' ]  = $this->data->getFile();
            $row[ 'errors' ][ 0 ][ 'line' ]  = $this->data->getLine();
            $row[ 'errors' ][ 0 ][ 'trace' ] = $this->data->getTrace();
            if ($this->data->getPrevious()) {
                $row[ 'errors' ][ 0 ][ 'caused_by' ][ 'message' ] = $this->data->getPrevious()->getMessage();
                $row[ 'errors' ][ 0 ][ 'caused_by' ][ 'code' ]    = $this->data->getPrevious()->getCode();
                $row[ 'errors' ][ 0 ][ 'caused_by' ][ 'file' ]    = $this->data->getPrevious()->getFile();
                $row[ 'errors' ][ 0 ][ 'caused_by' ][ 'line' ]    = $this->data->getPrevious()->getLine();
                $row[ 'errors' ][ 0 ][ 'caused_by' ][ 'trace' ]   = $this->data->getPrevious()->getTrace();
            }
        }

        $row[ 'errors' ][ 0 ][ 'code' ]    = $code;
        $row[ 'errors' ][ 0 ][ 'message' ] = $output;

        return $row;
    }

    public function jsonSerialize(): array
    {
        return $this->render();
    }


}