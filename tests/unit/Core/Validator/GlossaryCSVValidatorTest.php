<?php


namespace Matecat\Core\Validator;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Validator\Contracts\ValidatorObject;
use Utils\Validator\GlossaryCSVValidator;

class GlossaryCSVValidatorTest extends AbstractTest
{

    /**
     * @throws Exception
     */
    #[Test]
    public function testFiles()
    {
        $invalids = [
            self::projectRoot() . '/tests/resources/files/csv/glossary/NV - Header vuoto.csv',
            self::projectRoot() . '/tests/resources/files/csv/glossary/NV - Campi concetto + una sola lingua solo termini.csv',
            self::projectRoot() . '/tests/resources/files/csv/glossary/NV - Formato campi concetto + una sola lingua.csv',
            self::projectRoot() . '/tests/resources/files/csv/glossary/NV - Formato campi concetto + una sola lingua solo esempi.csv',
            self::projectRoot() . '/tests/resources/files/csv/glossary/NV - Formato campi concetto + una sola lingua solo note.csv',
            self::projectRoot() . '/tests/resources/files/csv/glossary/NV - Formato una sola lingua completa.csv',
            self::projectRoot() . '/tests/resources/files/csv/glossary/NV - Formato una sola lingua solo esempi.csv',
            self::projectRoot() . '/tests/resources/files/csv/glossary/NV - Formato una sola lingua solo note.csv',
            self::projectRoot() . '/tests/resources/files/csv/glossary/NV -Formato solo blacklist generale.csv',
        ];

        $valids = [
            AbstractTest::projectRoot() . '/tests/resources/files/csv/glossary/V - Header-vuoti.csv',
            AbstractTest::projectRoot() . '/tests/resources/files/csv/glossary/V - Formato completo.csv',
            AbstractTest::projectRoot() . '/tests/resources/files/csv/glossary/V - Formato lingue + campi termine.csv',
            AbstractTest::projectRoot() . '/tests/resources/files/csv/glossary/V - Formato lingue + campi termine (non per tutte le lingue).csv',
            AbstractTest::projectRoot() . '/tests/resources/files/csv/glossary/V - Formato semplice solo lingue.csv',
            AbstractTest::projectRoot() . '/tests/resources/files/csv/glossary/V - Formato solo blacklist combinata.csv',
            AbstractTest::projectRoot() . '/tests/resources/files/csv/glossary/V - Formato solo blacklist language-specific.csv',
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