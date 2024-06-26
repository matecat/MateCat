<?php

use TestHelpers\AbstractTest;
use Validator\GlossaryCSVValidator;
use Validator\GlossaryCSVValidatorObject;

class GlossaryCSVValidatorTest extends AbstractTest {

    public function testFiles()
    {
        $invalids = [
            __DIR__ . '/../../resources/files/csv/glossary/NV - Header vuoto.csv',
            __DIR__ . '/../../resources/files/csv/glossary/NV - Campi concetto + una sola lingua solo termini.csv',
            __DIR__ . '/../../resources/files/csv/glossary/NV - Formato campi concetto + una sola lingua.csv',
            __DIR__ . '/../../resources/files/csv/glossary/NV - Formato campi concetto + una sola lingua solo esempi.csv',
            __DIR__ . '/../../resources/files/csv/glossary/NV - Formato campi concetto + una sola lingua solo note.csv',
            __DIR__ . '/../../resources/files/csv/glossary/NV - Formato una sola lingua completa.csv',
            __DIR__ . '/../../resources/files/csv/glossary/NV - Formato una sola lingua solo esempi.csv',
            __DIR__ . '/../../resources/files/csv/glossary/NV - Formato una sola lingua solo note.csv',
            __DIR__ . '/../../resources/files/csv/glossary/NV -Formato solo blacklist generale.csv',
        ];

        $valids = [
            __DIR__ . '/../../resources/files/csv/glossary/V - Header-vuoti.csv',
            __DIR__ . '/../../resources/files/csv/glossary/V - Formato completo.csv',
            __DIR__ . '/../../resources/files/csv/glossary/V - Formato lingue + campi termine.csv',
            __DIR__ . '/../../resources/files/csv/glossary/V - Formato lingue + campi termine (non per tutte le lingue).csv',
            __DIR__ . '/../../resources/files/csv/glossary/V - Formato semplice solo lingue.csv',
            __DIR__ . '/../../resources/files/csv/glossary/V - Formato solo blacklist combinata.csv',
            __DIR__ . '/../../resources/files/csv/glossary/V - Formato solo blacklist language-specific.csv',
        ];

        foreach ($invalids as $invalid){
            $validatorObject = new GlossaryCSVValidatorObject();
            $validatorObject->csv = $invalid;
            $validator = new GlossaryCSVValidator();

            $validator->validate($validatorObject);

            $this->assertFalse($validator->isValid());
        }

        foreach ($valids as $valid){
            $validatorObject = new GlossaryCSVValidatorObject();
            $validatorObject->csv = $valid;
            $validator = new GlossaryCSVValidator();

            $validator->validate($validatorObject);

            $this->assertTrue($validator->isValid());
        }
    }
}