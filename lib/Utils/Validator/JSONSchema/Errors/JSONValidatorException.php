<?php

namespace Validator\JSONSchema\Errors;

use Exception;
use JsonSerializable;
use Swaggest\JsonSchema\Exception\Error;
use Validator\Contracts\ValidatorExceptionInterface;

class JSONValidatorException extends Exception implements JsonSerializable, ValidatorExceptionInterface {
    /**
     * @var Error
     */
    private $error;

    /**
     * JSONValidatorException constructor.
     *
     * @param Error $error
     */
    public function __construct( Error $error ) {
        parent::__construct( "JSON Validation Error: " . $error->error );
        $this->error = $error;
    }

    /**
     * @return string
     */
    public function getError() {
        return $this->error->error;
    }

    /**
     * @return string[]
     */
    public function getSchemaPointers() {
        return $this->error->schemaPointers;
    }

    /**
     * @return string
     */
    public function getDataPointer() {
        return $this->error->dataPointer;
    }

    /**
     * @return string
     */
    public function getProcessingPath() {
        return $this->error->processingPath;
    }

    /**
     * @return Error[]
     */
    public function getSubErrors() {
        return $this->error->subErrors;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return [
                'error'          => $this->getError(),
                'schemaPointers' => $this->getSchemaPointers(),
                'dataPointer'    => $this->getDataPointer(),
                'processingPath' => $this->getProcessingPath(),
                'subErrors'      => $this->getSubErrors(),
        ];
    }
}
