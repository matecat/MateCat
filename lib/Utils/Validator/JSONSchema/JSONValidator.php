<?php

namespace Utils\Validator\JSONSchema;

use Exception;
use RuntimeException;
use Swaggest\JsonSchema\Context;
use Swaggest\JsonSchema\InvalidValue;
use Swaggest\JsonSchema\RemoteRefProvider;
use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\SchemaContract;
use Utils\Registry\AppConfig;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;

class JSONValidator extends AbstractValidator
{

    /**
     * The JSON schema
     *
     * @var SchemaContract
     */
    private SchemaContract $schemaContract;
    private bool $throwExceptions;

    /**
     * JSONSchemaValidator constructor.
     *
     * @param string $jsonSchema
     * @param bool $throwExceptions
     *
     * @throws InvalidValue
     * @throws \Swaggest\JsonSchema\Exception
     */
    public function __construct(string $jsonSchema, bool $throwExceptions = false)
    {
        if (is_file(AppConfig::$ROOT . '/inc/validation/schema/' . $jsonSchema)) {
            $jsonSchema = file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/' . $jsonSchema);
        } elseif (is_file($jsonSchema)) {
            $jsonSchema = file_get_contents($jsonSchema);
        }

        $this->schemaContract = Schema::import(
            static::getValidJSONSchema($jsonSchema),
            new Context(
                new class implements RemoteRefProvider {
                    public function getSchemaData($url): object
                    {
                        if (is_file($url)) {
                            $url = file_get_contents($url);
                        } elseif (is_file(AppConfig::$ROOT . '/inc/validation/schema/' . $url)) {
                            $url = file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/' . $url);
                        }

                        return JSONValidator::getValidJSONSchema($url);
                    }

                }
            )
        );
        $this->throwExceptions = $throwExceptions;
    }

    /**
     * @param string $jsonSchema
     *
     * @return object
     */
    public static function getValidJSONSchema(string $jsonSchema): object
    {
        $decoded = json_decode($jsonSchema);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('The JSON schema is not valid');
        }

        return $decoded;
    }

    /**
     * @param ValidatorObject $object
     *
     * @return JSONValidatorObject|null
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     */
    public function validate(ValidatorObject $object): ?JSONValidatorObject
    {
        try {
            /** @var JSONValidatorObject $object */
            $this->schemaContract->in($object->decode());
        } catch (InvalidValue $invalidValue) {
            if (!$this->throwExceptions) {
                $this->addException(new JSONValidatorException($invalidValue->inspect()));

                return null;
            }
            throw new JSONValidatorException($invalidValue->inspect());
        } catch (Exception $exception) {
            if (!$this->throwExceptions) {
                $this->addException(new JsonValidatorGenericException($exception->getMessage()));

                return null;
            }
            throw new JsonValidatorGenericException($exception->getMessage());
        }

        return $object;
    }
}