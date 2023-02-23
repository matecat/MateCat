<?php

namespace Validator;

use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\SchemaContract;
use Validator\Contracts\AbstractValidator;
use Validator\Contracts\ValidatorObject;
use Validator\Errors\JSONValidatorError;

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
     * @throws \Exception
     */
    public function __construct( $jsonSchema )
    {
        $this->isValidJSONSchema($jsonSchema);
        $this->schemaContract = Schema::import(json_decode($jsonSchema));
    }

    /**
     * @param $jsonSchema
     *
     * @throws \Exception
     */
    private function isValidJSONSchema($jsonSchema)
    {
        json_decode($jsonSchema);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('The JSON schema is not valid');
        }
    }

    /**
     * @param ValidatorObject $object
     * @return bool
     * @throws \Exception
     */
    public function validate( ValidatorObject $object ) {

        if(!$object instanceof JSONValidatorObject){
            throw new \Exception('Object given is not an instance of JSONValidatorObject');
        }

        try {
            $this->schemaContract->in(json_decode($object->json));
        } catch (InvalidValue $invalidValue){
            $error = new JSONValidatorError($invalidValue->inspect());
            $this->addError($error);

            return false;
        } catch (\Exception $exception) {
            $this->addError($exception->getMessage());

            return false;
        }

        return true;
    }
}