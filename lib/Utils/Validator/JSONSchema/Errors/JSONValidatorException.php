<?php

namespace Utils\Validator\JSONSchema\Errors;

use Exception;
use JsonSerializable;
use Swaggest\JsonSchema\Exception\Error;

class JSONValidatorException extends Exception implements JsonSerializable
{
    /**
     * @var Error
     */
    private Error $error;

    /**
     * JSONValidatorException constructor.
     *
     * @param Error $error
     */
    public function __construct(Error $error)
    {
        parent::__construct("JSON Validation Error: " . $error->error);
        $this->error = $error;
    }

    /**
     * @return string
     */
    public function getError(): string
    {
        return $this->error->error;
    }

    /**
     * @return string[]
     */
    public function getSchemaPointers(): array
    {
        return $this->error->schemaPointers;
    }

    /**
     * @return string
     */
    public function getDataPointer(): string
    {
        return $this->error->dataPointer;
    }

    /**
     * @return string
     */
    public function getProcessingPath(): string
    {
        return $this->error->processingPath;
    }

    /**
     * @return Error[]
     */
    public function getSubErrors(): array
    {
        return $this->error->subErrors;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
                'error'          => $this->getError(),
                'schemaPointers' => $this->getSchemaPointers(),
                'dataPointer'    => $this->getDataPointer(),
                'processingPath' => $this->getProcessingPath(),
                'subErrors'      => $this->getSubErrors(),
        ];
    }
}
