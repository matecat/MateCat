<?php

use Swaggest\JsonSchema\InvalidValue;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class JSONValidatorTest extends AbstractTest {

    public function testInvalidSchema() {
        $jsonSchema = file_get_contents( __DIR__ . '/../../resources/files/json/schema/invalid.json' );

        try {
            $validator = new JSONValidator( $jsonSchema );
        } catch ( Exception $exception ) {
            $this->assertEquals( 'The JSON schema is not valid', $exception->getMessage() );
        }
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    public function testInvalidJsonPayload() {
        $jsonSchema  = file_get_contents( __DIR__ . '/../../resources/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents( __DIR__ . '/../../resources/files/json/files/invalid.json' );

        $validatorObject = new JSONValidatorObject( $invalidFile );
        $validator       = new JSONValidator( $jsonSchema );
        $validator->validate( $validatorObject );

        $this->assertFalse( $validator->isValid() );
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    public function testInvalidJsonPayloadWithException() {
        $jsonSchema  = file_get_contents( __DIR__ . '/../../resources/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents( __DIR__ . '/../../resources/files/json/files/invalid.json' );

        $validatorObject = new JSONValidatorObject( $invalidFile );
        $validator       = new JSONValidator( $jsonSchema, true );

        $this->expectException( JSONValidatorException::class );
        $validator->validate( $validatorObject );
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    public function testJsonPayloadNotValidatedWithException() {
        $jsonSchema  = file_get_contents( __DIR__ . '/../../resources/files/json/schema/schema_1.json' );
        $invalidFile = '{"name": "John"}';

        $validatorObject = new JSONValidatorObject( $invalidFile );
        $validator       = new JSONValidator( $jsonSchema, true );

        $this->expectException( JSONValidatorException::class );
        $validator->validate( $validatorObject );
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    public function testInvalidMaxItemsFile() {
        $jsonSchema  = file_get_contents( __DIR__ . '/../../resources/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents( __DIR__ . '/../../resources/files/json/files/invalid_maxItems.json' );

        $validatorObject = new JSONValidatorObject( $invalidFile );
        $validator       = new JSONValidator( $jsonSchema );
        $validator->validate( $validatorObject );

        $error = $validator->getExceptions()[ 0 ];

        $this->assertFalse( $validator->isValid() );
        $this->assertEquals( "JSON Validation Error: Too many items in array", $error->getMessage() );
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    public function testValidFile() {
        $jsonSchema  = file_get_contents( __DIR__ . '/../../resources/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents( __DIR__ . '/../../resources/files/json/files/valid.json' );

        $validatorObject = new JSONValidatorObject( $invalidFile );
        $validator       = new JSONValidator( $jsonSchema );
        $validator->validate( $validatorObject );

        $this->assertTrue( $validator->isValid() );
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    public function testValidUberQAModelFile() {
        $jsonSchema  = file_get_contents( AppConfig::$ROOT . '/inc/validation/schema/qa_model.json' );
        $uberQaModel = file_get_contents( __DIR__ . '/../../resources/files/json/files/uber_qa_model.json' );

        $validatorObject = new JSONValidatorObject( $uberQaModel );
        $validator       = new JSONValidator( $jsonSchema );
        $validator->validate( $validatorObject );

        $this->assertTrue( $validator->isValid() );
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    public function testLoadsSchemaFromAppRootSchemaFolder() {
        $schemaFileName = 'qa_model.json';

        // Arrange: pick a known schema that exists under AppConfig::$ROOT/inc/validation/schema
        $schemaAbsPath = AppConfig::$ROOT . '/inc/validation/schema/' . $schemaFileName;
        $this->assertFileExists( $schemaAbsPath, 'Fixture missing: ' . $schemaAbsPath );

        // Minimal valid content against qa_model.json: wrap a basic model payload
        $validPayload = json_encode( [ 'model' => [ 'version' => 1, 'categories' => [], 'label' => 'foo', 'passfail' => [ 'type' => 'points_per_thousand', 'thresholds' => [ [ 'label' => 'R1', 'value' => 1 ] ] ] ] ], JSON_UNESCAPED_SLASHES );

        // Act
        $validatorObject = new JSONValidatorObject( $validPayload );
        $validator       = new JSONValidator( $schemaFileName, true ); // triggers the root-folder branch
        $validator->validate( $validatorObject );

        // Assert
        $this->assertTrue( $validator->isValid(), 'Validation should pass when schema is loaded from AppConfig::$ROOT path' );
    }

    /**
     * @throws \Swaggest\JsonSchema\Exception
     * @throws JSONValidatorException
     * @throws InvalidValue
     * @throws JsonValidatorGenericException
     */
    public function testLoadsSchemaFromAbsolutePath() {
        // Arrange: use an existing schema but pass its absolute path (second branch)
        $schemaAbsPath = AppConfig::$ROOT . '/inc/validation/schema/qa_model.json';
        $this->assertFileExists( $schemaAbsPath, 'Fixture missing: ' . $schemaAbsPath );

        $validPayload = json_encode( [ 'model' => [ 'version' => 1, 'categories' => [], 'label' => 'foo', 'passfail' => [ 'type' => 'points_per_thousand', 'thresholds' => [ [ 'label' => 'R1', 'value' => 1 ] ] ] ] ], JSON_UNESCAPED_SLASHES );

        // Act
        $validatorObject = new JSONValidatorObject( $validPayload );
        $validator       = new JSONValidator( $schemaAbsPath, true ); // triggers the absolute path branch
        $validator->validate( $validatorObject );

        // Assert
        $this->assertTrue( $validator->isValid(), 'Validation should pass when schema is loaded from absolute path' );
    }

}