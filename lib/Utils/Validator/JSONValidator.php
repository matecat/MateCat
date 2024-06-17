<?php

namespace Validator;

use Exception;
use INIT;
use Swaggest\JsonSchema\Context;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\RemoteRefProvider;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\SchemaContract;
use Validator\Contracts\AbstractValidator;
use Validator\Contracts\ValidatorObject;
use Validator\Errors\JSONValidatorException;
use Validator\Errors\JsonValidatorGenericException;

class JSONValidator extends AbstractValidator {

    /**
     * The JSON schema
     *
     * @var SchemaContract
     */
    private $schemaContract;

    /**
     * JSONSchemaValidator constructor.
     *
     * @param $jsonSchema
     *
     * @throws InvalidValue
     * @throws Exception
     */
    public function __construct( $jsonSchema ) {
        $this->isValidJSONSchema( $jsonSchema );
        $this->schemaContract = Schema::import( json_decode( $jsonSchema ), new Context( new class implements RemoteRefProvider {
            public function getSchemaData( $url ) {
                return json_decode( file_get_contents( INIT::$ROOT . '/inc/validation/schema/' . $url ) );
            }

        }) );
    }

    /**
     * @param $jsonSchema
     *
     * @throws Exception
     */
    private function isValidJSONSchema( $jsonSchema ) {
        json_decode( $jsonSchema );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new Exception( 'The JSON schema is not valid' );
        }
    }

    /**
     * @param ValidatorObject $object
     *
     * @return bool
     * @throws Exception
     */
    public function validate( ValidatorObject $object ) {

        if ( !$object instanceof JSONValidatorObject ) {
            throw new Exception( 'Object given is not an instance of JSONValidatorObject' );
        }

        try {
            $this->schemaContract->in( json_decode( $object->json ) );
        } catch ( InvalidValue $invalidValue ) {
            $this->addException( new JSONValidatorException( $invalidValue->inspect() ) );

            return false;
        } catch ( Exception $exception ) {
            $this->addException( new JsonValidatorGenericException( $exception->getMessage() ) );

            return false;
        }

        return true;
    }
}