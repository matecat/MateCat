<?php

namespace Validator\Errors;

use Swaggest\JsonSchema\Exception\Error;

class JSONValidatorError implements \JsonSerializable
{
    /**
     * @var Error
     */
    private $error;

    /**
     * JSONValidatorError constructor.
     *
     * @param Error $error
     */
    public function __construct( Error $error) {
        $this->error = $error;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'error' => $this->error->error,
            'schemaPointers' => $this->error->schemaPointers,
            'dataPointer' => $this->error->dataPointer,
            'processingPath' => $this->error->processingPath,
            'subErrors' => $this->error->subErrors,
        ];
    }
}
