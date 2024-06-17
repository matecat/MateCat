<?php
require( '/home/hashashiyyin/PhpstormProjects/matecat/inc/Bootstrap.php' );
Bootstrap::start();

use Validator\JSONValidator;
use Validator\JSONValidatorObject;
class JSONValidatorTest extends PHPUnit_Framework_TestCase {

    public function testInvalidSchema()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../support/files/json/schema/invalid.json' );

        try {
            $validator = new JSONValidator($jsonSchema);
        } catch (\Exception $exception){
            $this->assertEquals($exception->getMessage(), 'The JSON schema is not valid');
        }
    }

    public function testInvalidFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../support/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents(__DIR__.'/../../support/files/json/files/invalid.json');

        $validatorObject = new JSONValidatorObject();
        $validatorObject->json = $invalidFile;
        $validator = new JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $this->assertFalse($validator->isValid());
    }

    public function testInvalidMaxItemsFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../support/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents(__DIR__.'/../../support/files/json/files/invalid_maxItems.json');

        $validatorObject = new JSONValidatorObject();
        $validatorObject->json = $invalidFile;
        $validator = new JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $error = $validator->getExceptions()[0]->error;

        $this->assertFalse($validator->isValid());
        $this->assertEquals("JSON Validation Error: Too many items in array", $error->getMessage());
    }

    public function testValidFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../support/files/json/schema/schema_1.json' );
        $invalidFile = file_get_contents(__DIR__.'/../../support/files/json/files/valid.json');

        $validatorObject = new JSONValidatorObject();
        $validatorObject->json = $invalidFile;
        $validator = new JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $this->assertTrue($validator->isValid());
    }

    public function testValidUberQAModelFile()
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../../inc/qa_model.json' );
        $uberQaModel = file_get_contents(__DIR__.'/../../support/files/json/files/uber_qa_model.json');

        $validatorObject = new JSONValidatorObject();
        $validatorObject->json = $uberQaModel;
        $validator = new JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        $this->assertTrue($validator->isValid());
    }
}