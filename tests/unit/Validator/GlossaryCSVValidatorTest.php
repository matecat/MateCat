<?php

use TestHelpers\AbstractTest;
use Utils\Validator\Contracts\ValidatorObject;
use Utils\Validator\GlossaryCSVValidator;

class GlossaryCSVValidatorTest extends AbstractTest
{

    /**
     * @throws Exception
     */
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

        foreach ($invalids as $invalid) {
            $validator = new GlossaryCSVValidator();
            $validator->validate(ValidatorObject::fromArray([
                'csv' => $invalid
            ]));

            $this->assertFalse($validator->isValid());
        }

        foreach ($valids as $valid) {
            $validator = new GlossaryCSVValidator();
            $validator->validate(ValidatorObject::fromArray([
                'csv' => $valid
            ]));

            $this->assertTrue($validator->isValid());
        }
    }
}