<?php

use Validator\GlossaryCSVValidatorObject;

class GlossaryCSVValidatorTest extends PHPUnit_Framework_TestCase {


    public function testInvalidLanguages()
    {
        $csv = __DIR__ . '/../../support/files/csv/glossary/invalid-language.csv';

        $validatorObject = new GlossaryCSVValidatorObject();
        $validatorObject->csv = $csv;
        $validator = new \Validator\GlossaryCSVValidator();

        $validator->validate($validatorObject);

        $this->assertFalse($validator->isValid());
    }

    public function testInvalidStructure()
    {
        $csv =  __DIR__ . '/../../support/files/csv/glossary/invalid-structure.csv';

        $validatorObject = new GlossaryCSVValidatorObject();
        $validatorObject->csv = $csv;
        $validator = new \Validator\GlossaryCSVValidator();

        $validator->validate($validatorObject);

        $this->assertFalse($validator->isValid());
    }

    public function testMinimalValidFile()
    {
        $csv = __DIR__ . '/../../support/files/csv/glossary/minimal-valid.csv';

        $validatorObject = new GlossaryCSVValidatorObject();
        $validatorObject->csv = $csv;
        $validator = new \Validator\GlossaryCSVValidator();

        $validator->validate($validatorObject);

        $this->assertTrue($validator->isValid());
    }

    public function testFullValidFile()
    {
        $csv = __DIR__ . '/../../support/files/csv/glossary/full-structure-valid.csv';

        $validatorObject = new GlossaryCSVValidatorObject();
        $validatorObject->csv = $csv;
        $validator = new \Validator\GlossaryCSVValidator();

        $validator->validate($validatorObject);

        $this->assertTrue($validator->isValid());
    }
}