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

        $validator = new \Validator\JSONValidator($jsonSchema);

        $this->assertFalse($validator->isValid($invalidFile));
    }

    public function testInvalidMaxItemsFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../support/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents(__DIR__.'/../../support/files/json/files/invalid_maxItems.json');

        $validator = new \Validator\JSONValidator($jsonSchema);
        $error = $validator->validate($invalidFile)[0];

        $this->assertFalse($validator->isValid($invalidFile));
        $this->assertEquals("Too many items in array", $error->getError());
    }

    public function testValidFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../support/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents(__DIR__.'/../../support/files/json/files/valid.json');

        $validator = new \Validator\JSONValidator($jsonSchema);

        $this->assertTrue($validator->isValid($invalidFile));
    }

    public function testValidUberQAModelFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../../inc/qa_model/schema.json' );
        $uberQaModel = file_get_contents(__DIR__.'/../../support/files/json/files/uber_qa_model.json');

        $validator = new \Validator\JSONValidator($jsonSchema);

        $this->assertTrue($validator->isValid($uberQaModel));
    }
}