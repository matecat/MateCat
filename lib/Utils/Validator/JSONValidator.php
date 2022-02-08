<?php

namespace Validator;

use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\SchemaContract;
use Validator\Contracts\ValidatorObject;
use Validator\Errors\JSONValidatorError;

class JSONValidator extends ValidatorObject {

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
     * @param $json
     *
     * @return bool
     */
    public function isValid($json)
    {
        return empty($this->validate($json));
    }

    /**
     * @param $json
     *
     * @return array
     */
    public function validate($json)
    {
        $errors = [];

        try {
            $this->schemaContract->in(json_decode($json));
        } catch (InvalidValue $invalidValue){
            $errors[] = new JSONValidatorError($invalidValue->inspect());
        } catch (\Exception $exception) {
            $errors[] = [
                'error' => $exception->getMessage()
            ];
        }

        return $errors;
    }
}