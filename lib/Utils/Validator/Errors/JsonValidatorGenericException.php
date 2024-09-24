<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 05/06/24
 * Time: 19:04
 *
 */

namespace Validator\Errors;

use Exception;
use JsonSerializable;

class JsonValidatorGenericException extends Exception implements JsonSerializable, JsonValidatorExceptionInterface {

    private $error;

    /**
     * @param $error
     */
    public function __construct( $error ) {
        parent::__construct( $error );
        $this->error = $error;
    }


    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return $this->error;
    }

}