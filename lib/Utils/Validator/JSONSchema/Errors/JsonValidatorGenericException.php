<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 05/06/24
 * Time: 19:04
 *
 */

namespace Utils\Validator\JSONSchema\Errors;

use Exception;
use JsonSerializable;

class JsonValidatorGenericException extends Exception implements JsonSerializable
{

    private ?string $error;

    /**
     * @param string|null $error
     */
    public function __construct(?string $error = null)
    {
        parent::__construct($error);
        $this->error = $error;
    }


    /**
     * @inheritDoc
     */
    public function jsonSerialize(): ?string
    {
        return $this->error;
    }

}