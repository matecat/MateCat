<?php

namespace Utils\Validator\JSONSchema;

use Exception;
use INIT;
use RuntimeException;
use Swaggest\JsonSchema\Context;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\RemoteRefProvider;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\SchemaContract;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;

class JSONValidator extends AbstractValidator {

    /**
     * The JSON schema
     *
     * @var SchemaContract
     */
    private SchemaContract $schemaContract;
    private bool           $throwExceptions;

    /**
     * JSONSchemaValidator constructor.
     *
     * @param string $jsonSchema
     * @param bool   $throwExceptions
     *
     * @throws InvalidValue
     * @throws \Swaggest\JsonSchema\Exception
     */
    public function __construct( string $jsonSchema, bool $throwExceptions = false ) {
        $this->schemaContract  = Schema::import( static::getValidJSONSchema( $jsonSchema ), new Context( new class implements RemoteRefProvider {
            public function getSchemaData( $url ): object {
                return JSONValidator::getValidJSONSchema( file_get_contents( INIT::$ROOT . '/inc/validation/schema/' . $url ) );
            }

        } ) );
        $this->throwExceptions = $throwExceptions;
    }

    /**
     * @param string $jsonSchema
     *
     * @return object
     */
    public static function getValidJSONSchema( string $jsonSchema ): object {
        $decoded = json_decode( $jsonSchema );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new RuntimeException( 'The JSON schema is not valid' );
        }

        return $decoded;
    }

    /**
     * @param ValidatorObject $object
     *
     * @return ValidatorObject|null
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     */
    public function validate( ValidatorObject $object ): ?ValidatorObject {

        /** @var JSONValidatorObject $object */
        $object->decoded = json_decode( $object->json );

        try {
            $this->schemaContract->in( $object->decoded );
        } catch ( InvalidValue $invalidValue ) {
            if ( !$this->throwExceptions ) {
                $this->addException( new JSONValidatorException( $invalidValue->inspect() ) );

                return null;
            }
            throw new JSONValidatorException( $invalidValue->inspect() );

        } catch ( Exception $exception ) {
            if ( !$this->throwExceptions ) {
                $this->addException( new JsonValidatorGenericException( $exception->getMessage() ) );

                return null;
            }
            throw new JsonValidatorGenericException( $exception->getMessage() );
        }

        return $object;
    }
}