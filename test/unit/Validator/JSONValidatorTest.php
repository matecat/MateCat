<?php

class JSONValidatorTest extends PHPUnit_Framework_TestCase {

    public function testInvalidSchema()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../support/files/json/schema/invalid.json' );

        try {
            $validator = new \Validator\JSONValidator($jsonSchema);
        } catch (\Exception $exception){
            $this->assertEquals($exception->getMessage(), 'The JSON schema is not valid');
        }
    }

    public function testInvalidFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../support/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents(__DIR__.'/../../support/files/json/files/invalid.json');

        $validatorObject = new \Validator\JSONValidatorObject();
        $validatorObject->json = $invalidFile;
        $validator = new \Validator\JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $this->assertFalse($validator->isValid());
    }

    public function testInvalidMaxItemsFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../support/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents(__DIR__.'/../../support/files/json/files/invalid_maxItems.json');

        $validatorObject = new \Validator\JSONValidatorObject();
        $validatorObject->json = $invalidFile;
        $validator = new \Validator\JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $error = $validator->getErrors()[0]->error;

        $this->assertFalse($validator->isValid());
        $this->assertEquals("JSON Validation Error: Too many items in array", $error->getMessage());
    }

    public function testValidFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../support/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents(__DIR__.'/../../support/files/json/files/valid.json');

        $validatorObject = new \Validator\JSONValidatorObject();
        $validatorObject->json = $invalidFile;
        $validator = new \Validator\JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $this->assertTrue($validator->isValid());
    }

    public function testValidUberQAModelFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../../inc/qa_model/schema.json' );
        $uberQaModel = file_get_contents(__DIR__.'/../../support/files/json/files/uber_qa_model.json');

        $validatorObject = new \Validator\JSONValidatorObject();
        $validatorObject->json = $uberQaModel;
        $validator = new \Validator\JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $this->assertTrue($validator->isValid());
    }
}