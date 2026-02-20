<?php

namespace unit\Validator;

use Exception;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Swaggest\JsonSchema\InvalidValue;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class JSONValidatorTest extends AbstractTest
{

    #[Test]
    public function testInvalidSchema(): void
    {
        $jsonSchema = file_get_contents(__DIR__ . '/../../resources/files/json/schema/invalid.json');

        try {
            new JSONValidator($jsonSchema);
        } catch (Exception $exception) {
            $this->assertEquals('The JSON schema is not valid', $exception->getMessage());
        }
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testInvalidJsonPayload(): void
    {
        $jsonSchema = file_get_contents(__DIR__ . '/../../resources/files/json/schema/schema_1.json');
        $invalidFile = file_get_contents(__DIR__ . '/../../resources/files/json/files/invalid.json');

        $validatorObject = new JSONValidatorObject($invalidFile);
        $validator = new JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $this->assertFalse($validator->isValid());
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testInvalidJsonPayloadWithException(): void
    {
        $jsonSchema = file_get_contents(__DIR__ . '/../../resources/files/json/schema/schema_1.json');
        $invalidFile = file_get_contents(__DIR__ . '/../../resources/files/json/files/invalid.json');

        $validatorObject = new JSONValidatorObject($invalidFile);
        $validator = new JSONValidator($jsonSchema, true);

        $this->expectException(JSONValidatorException::class);
        $validator->validate($validatorObject);
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testJsonPayloadNotValidatedWithException(): void
    {
        $jsonSchema = file_get_contents(__DIR__ . '/../../resources/files/json/schema/schema_1.json');
        $invalidFile = '{"name": "John"}';

        $validatorObject = new JSONValidatorObject($invalidFile);
        $validator = new JSONValidator($jsonSchema, true);

        $this->expectException(JSONValidatorException::class);
        $validator->validate($validatorObject);
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testInvalidMaxItemsFile(): void
    {
        $jsonSchema = file_get_contents(__DIR__ . '/../../resources/files/json/schema/schema_1.json');
        $invalidFile = file_get_contents(__DIR__ . '/../../resources/files/json/files/invalid_maxItems.json');

        $validatorObject = new JSONValidatorObject($invalidFile);
        $validator = new JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $error = $validator->getExceptions()[0];

        $this->assertFalse($validator->isValid());
        $this->assertEquals("JSON Validation Error: Too many items in array", $error->getMessage());
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testValidFile(): void
    {
        $jsonSchema = file_get_contents(__DIR__ . '/../../resources/files/json/schema/schema_1.json');
        $invalidFile = file_get_contents(__DIR__ . '/../../resources/files/json/files/valid.json');

        $validatorObject = new JSONValidatorObject($invalidFile);
        $validator = new JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $this->assertTrue($validator->isValid());
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testValidUberQAModelFile(): void
    {
        $jsonSchema = file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/qa_model.json');
        $uberQaModel = file_get_contents(__DIR__ . '/../../resources/files/json/files/uber_qa_model.json');

        $validatorObject = new JSONValidatorObject($uberQaModel);
        $validator = new JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $this->assertTrue($validator->isValid());
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testLoadsSchemaFromAppRootSchemaFolder(): void
    {
        $schemaFileName = 'qa_model.json';

        // Arrange: pick a known schema that exists under AppConfig::$ROOT/inc/validation/schema
        $schemaAbsPath = AppConfig::$ROOT . '/inc/validation/schema/' . $schemaFileName;
        $this->assertFileExists($schemaAbsPath, 'Fixture missing: ' . $schemaAbsPath);

        // Minimal valid content against qa_model.json: wrap a basic model payload
        $validPayload = json_encode(
            ['model' => ['version' => 1, 'categories' => [], 'label' => 'foo', 'passfail' => ['type' => 'points_per_thousand', 'thresholds' => [['label' => 'R1', 'value' => 1]]]]],
            JSON_UNESCAPED_SLASHES
        );

        // Act
        $validatorObject = new JSONValidatorObject($validPayload);
        $validator = new JSONValidator($schemaFileName, true); // triggers the root-folder branch
        $validator->validate($validatorObject);

        // Assert
        $this->assertTrue($validator->isValid(), 'Validation should pass when schema is loaded from AppConfig::$ROOT path');
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testLoadsSchemaFromAbsolutePath(): void
    {
        // Arrange: use an existing schema but pass its absolute path (second branch)
        $schemaAbsPath = AppConfig::$ROOT . '/inc/validation/schema/qa_model.json';
        $this->assertFileExists($schemaAbsPath, 'Fixture missing: ' . $schemaAbsPath);

        $validPayload = json_encode(
            ['model' => ['version' => 1, 'categories' => [], 'label' => 'foo', 'passfail' => ['type' => 'points_per_thousand', 'thresholds' => [['label' => 'R1', 'value' => 1]]]]],
            JSON_UNESCAPED_SLASHES
        );

        // Act
        $validatorObject = new JSONValidatorObject($validPayload);
        $validator = new JSONValidator($schemaAbsPath, true); // triggers the absolute path branch
        $validator->validate($validatorObject);

        // Assert
        $this->assertTrue($validator->isValid(), 'Validation should pass when schema is loaded from absolute path');
    }

    // ========== JSONValidatorObject Tests ==========

    /**
     * @throws Exception
     */
    #[Test]
    public function testDecodeReturnsDecodedJson(): void
    {
        $json = '{"name": "John", "age": 30}';
        $validatorObject = new JSONValidatorObject($json);

        $decoded = $validatorObject->decode();

        $this->assertIsObject($decoded);
        $this->assertEquals('John', $decoded->name);
        $this->assertEquals(30, $decoded->age);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testDecodeMemoizesResult(): void
    {
        $json = '{"name": "John"}';
        $validatorObject = new JSONValidatorObject($json);

        $decoded1 = $validatorObject->decode();
        $decoded2 = $validatorObject->decode();

        // Should return the same memoized result
        $this->assertSame($decoded1, $decoded2);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testDecodeEmptyStringReturnsNull(): void
    {
        $validatorObject = new JSONValidatorObject('');

        $decoded = $validatorObject->decode();

        $this->assertNull($decoded);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testDecodeNullJsonReturnsNull(): void
    {
        $validatorObject = new JSONValidatorObject('null');

        $decoded = $validatorObject->decode();

        $this->assertNull($decoded);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testDecodeArrayJson(): void
    {
        $json = '[1, 2, 3]';
        $validatorObject = new JSONValidatorObject($json);

        $decoded = $validatorObject->decode();

        $this->assertIsArray($decoded);
        $this->assertEquals([1, 2, 3], $decoded);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testDecodeScalarJson(): void
    {
        $validatorObject = new JSONValidatorObject('"hello"');
        $decoded = $validatorObject->decode();
        $this->assertEquals('hello', $decoded);

        $validatorObject2 = new JSONValidatorObject('42');
        $decoded2 = $validatorObject2->decode();
        $this->assertEquals(42, $decoded2);

        $validatorObject3 = new JSONValidatorObject('true');
        $decoded3 = $validatorObject3->decode();
        $this->assertTrue($decoded3);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testDecodeInvalidJsonThrowsException(): void
    {
        $validatorObject = new JSONValidatorObject('{invalid json}');

        $this->expectException(JsonException::class);
        $validatorObject->decode();
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetValueWithoutAssociativeReturnsObject(): void
    {
        $json = '{"name": "John", "age": 30}';
        $validatorObject = new JSONValidatorObject($json);

        $value = $validatorObject->getValue();

        $this->assertIsObject($value);
        $this->assertEquals('John', $value->name);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetValueWithAssociativeReturnsArray(): void
    {
        $json = '{"name": "John", "age": 30}';
        $validatorObject = new JSONValidatorObject($json);

        $value = $validatorObject->getValue(true);

        $this->assertIsArray($value);
        $this->assertEquals('John', $value['name']);
        $this->assertEquals(30, $value['age']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetValueWithNullReturnsNull(): void
    {
        $validatorObject = new JSONValidatorObject('null');

        $value = $validatorObject->getValue(true);

        $this->assertNull($value);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetValueWithNestedObjectsAssociative(): void
    {
        $json = '{"person": {"name": "John", "address": {"city": "NYC", "zip": "10001"}}}';
        $validatorObject = new JSONValidatorObject($json);

        $value = $validatorObject->getValue(true);

        $this->assertIsArray($value);
        $this->assertIsArray($value['person']);
        $this->assertEquals('John', $value['person']['name']);
        $this->assertIsArray($value['person']['address']);
        $this->assertEquals('NYC', $value['person']['address']['city']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetValueWithNestedArraysAssociative(): void
    {
        $json = '{"items": [{"id": 1, "name": "A"}, {"id": 2, "name": "B"}]}';
        $validatorObject = new JSONValidatorObject($json);

        $value = $validatorObject->getValue(true);

        $this->assertIsArray($value);
        $this->assertIsArray($value['items']);
        $this->assertCount(2, $value['items']);
        $this->assertEquals(1, $value['items'][0]['id']);
        $this->assertEquals('B', $value['items'][1]['name']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetValueWithMixedNestedStructure(): void
    {
        $json = '{"data": {"users": [{"name": "John", "tags": ["admin", "user"]}, {"name": "Jane", "tags": ["user"]}], "count": 2}}';
        $validatorObject = new JSONValidatorObject($json);

        $value = $validatorObject->getValue(true);

        $this->assertIsArray($value);
        $this->assertIsArray($value['data']);
        $this->assertIsArray($value['data']['users']);
        $this->assertEquals('John', $value['data']['users'][0]['name']);
        $this->assertIsArray($value['data']['users'][0]['tags']);
        $this->assertEquals('admin', $value['data']['users'][0]['tags'][0]);
        $this->assertEquals(2, $value['data']['count']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetValueWithEmptyObjectAssociative(): void
    {
        $json = '{}';
        $validatorObject = new JSONValidatorObject($json);

        $value = $validatorObject->getValue(true);

        $this->assertIsArray($value);
        $this->assertEmpty($value);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetValueWithEmptyArrayAssociative(): void
    {
        $json = '[]';
        $validatorObject = new JSONValidatorObject($json);

        $value = $validatorObject->getValue(true);

        $this->assertIsArray($value);
        $this->assertEmpty($value);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetValuePreservesScalarTypes(): void
    {
        $json = '{"string": "hello", "int": 42, "float": 3.14, "bool": true, "null": null}';
        $validatorObject = new JSONValidatorObject($json);

        $value = $validatorObject->getValue(true);

        $this->assertIsString($value['string']);
        $this->assertIsInt($value['int']);
        $this->assertIsFloat($value['float']);
        $this->assertIsBool($value['bool']);
        $this->assertNull($value['null']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetValueWithDeeplyNestedStructure(): void
    {
        $json = '{"level1": {"level2": {"level3": {"level4": {"value": "deep"}}}}}';
        $validatorObject = new JSONValidatorObject($json);

        $value = $validatorObject->getValue(true);

        $this->assertEquals('deep', $value['level1']['level2']['level3']['level4']['value']);
    }

    // ========== Generic Exception Tests ==========

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws InvalidValue
     */
    #[Test]
    public function testValidateHandlesGenericExceptionWithoutThrowing(): void
    {
        $jsonSchema = file_get_contents(__DIR__ . '/../../resources/files/json/schema/schema_1.json');

        // Create a stub that throws a generic exception
        $validatorObject = $this->createStub(JSONValidatorObject::class);
        $validatorObject->method('decode')
            ->willThrowException(new Exception('Generic error'));

        $validator = new JSONValidator($jsonSchema, false);

        try {
            $validator->validate($validatorObject);
        } catch (JsonValidatorGenericException|JSONValidatorException) {
            // Should not throw when throwExceptions is false
            $this->fail('Should not throw when throwExceptions is false');
        }

        $this->assertFalse($validator->isValid());
        $exceptions = $validator->getExceptions();
        $this->assertCount(1, $exceptions);
        $this->assertInstanceOf(JsonValidatorGenericException::class, $exceptions[0]);
    }

    /**
     * @throws InvalidValue
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Swaggest\JsonSchema\Exception
     */
    #[Test]
    public function testValidateThrowsGenericExceptionWhenConfigured(): void
    {
        $jsonSchema = file_get_contents(__DIR__ . '/../../resources/files/json/schema/schema_1.json');

        // Create a stub that throws a generic exception
        $validatorObject = $this->createStub(JSONValidatorObject::class);
        $validatorObject->method('decode')
            ->willThrowException(new Exception('Generic error'));

        $validator = new JSONValidator($jsonSchema, true);

        $this->expectException(JsonValidatorGenericException::class);
        $this->expectExceptionMessage('Generic error');
        $validator->validate($validatorObject);
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testValidateReturnsObjectOnSuccess(): void
    {
        $jsonSchema = file_get_contents(__DIR__ . '/../../resources/files/json/schema/schema_1.json');
        $validFile = file_get_contents(__DIR__ . '/../../resources/files/json/files/valid.json');

        $validatorObject = new JSONValidatorObject($validFile);
        $validator = new JSONValidator($jsonSchema);
        $result = $validator->validate($validatorObject);

        $this->assertSame($validatorObject, $result);
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testValidateReturnsNullOnFailureWithoutThrowing(): void
    {
        $jsonSchema = file_get_contents(__DIR__ . '/../../resources/files/json/schema/schema_1.json');
        $invalidFile = file_get_contents(__DIR__ . '/../../resources/files/json/files/invalid.json');

        $validatorObject = new JSONValidatorObject($invalidFile);
        $validator = new JSONValidator($jsonSchema, false);
        $result = $validator->validate($validatorObject);

        $this->assertNull($result);
    }

    // ========== getValidJSONSchema Tests ==========

    #[Test]
    public function testGetValidJSONSchemaWithValidJson(): void
    {
        $json = '{"type": "object"}';
        $result = JSONValidator::getValidJSONSchema($json);

        $this->assertIsObject($result);
        $this->assertEquals('object', $result->type);
    }

    #[Test]
    public function testGetValidJSONSchemaThrowsOnInvalidJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The JSON schema is not valid');

        JSONValidator::getValidJSONSchema('{invalid}');
    }


    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    #[Test]
    public function testValidateRemoteReferenceInSchema(): void
    {
        $invalidFile = file_get_contents(__DIR__ . '/../../resources/files/json/files/xliff_params.json');

        $validatorObject = new JSONValidatorObject($invalidFile);
        $validator = new JSONValidator('xliff_parameters_rules_wrapper.json', false);
        $result = $validator->validate($validatorObject);

        $this->assertNotNull($result);
    }

}