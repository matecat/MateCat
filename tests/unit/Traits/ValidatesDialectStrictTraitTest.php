<?php

namespace unit\Traits;

use InvalidArgumentException;
use Matecat\Locales\Languages;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Controller\Traits\ValidatesDialectStrictTrait;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;

class DialectStrictValidator
{
    use ValidatesDialectStrictTrait;

    public function validate(Languages $lang_handler, mixed $dialect_strict = null): ?array
    {
        return $this->validateDialectStrictParam($lang_handler, $dialect_strict);
    }
}

class ValidatesDialectStrictTraitTest extends AbstractTest
{
    private DialectStrictValidator $validator;
    private Languages $langHandler;

    protected function setUp(): void
    {
        $this->validator = new DialectStrictValidator();
        $this->langHandler = Languages::getInstance();
    }

    #[Test]
    public function validSingleLanguageBooleanReturnsArray(): void
    {
        $json = json_encode(['it-IT' => true]);
        $result = $this->validator->validate($this->langHandler, $json);
        $this->assertIsArray($result);
        $this->assertSame(['it-IT' => true], $result);
    }

    #[Test]
    public function validMultipleLanguagesReturnsArray(): void
    {
        $json = json_encode(['it-IT' => true, 'en-US' => false, 'fr-FR' => true]);
        $result = $this->validator->validate($this->langHandler, $json);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertTrue($result['it-IT']);
        $this->assertFalse($result['en-US']);
        $this->assertTrue($result['fr-FR']);
    }

    #[Test]
    public function nullInputReturnsNull(): void
    {
        $this->assertNull($this->validator->validate($this->langHandler, null));
    }

    #[Test]
    public function emptyStringReturnsNull(): void
    {
        $this->assertNull($this->validator->validate($this->langHandler, ''));
    }

    #[Test]
    public function htmlEncodedInputIsDecoded(): void
    {
        $json = htmlentities(json_encode(['it-IT' => true]));
        $result = $this->validator->validate($this->langHandler, $json);
        $this->assertSame(['it-IT' => true], $result);
    }

    #[Test]
    public function invalidJsonThrowsException(): void
    {
        $this->expectException(JsonValidatorGenericException::class);
        $this->validator->validate($this->langHandler, '{not valid json}');
    }

    #[Test]
    public function nonBooleanValueThrowsException(): void
    {
        $this->expectException(JSONValidatorException::class);
        $json = json_encode(['it-IT' => 'string_value']);
        $this->validator->validate($this->langHandler, $json);
    }

    #[Test]
    public function integerValueThrowsException(): void
    {
        $this->expectException(JSONValidatorException::class);
        $json = json_encode(['it-IT' => 1]);
        $this->validator->validate($this->langHandler, $json);
    }

    #[Test]
    public function arrayPayloadThrowsException(): void
    {
        $this->expectException(JSONValidatorException::class);
        $this->validator->validate($this->langHandler, '[true, false]');
    }

    #[Test]
    public function keyNotMatchingPatternThrowsException(): void
    {
        $this->expectException(JSONValidatorException::class);
        // "italian" does not match the BCP 47 pattern
        $json = json_encode(['italian' => true]);
        $this->validator->validate($this->langHandler, $json);
    }

    #[Test]
    public function unsupportedLanguageCodeThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not supported');
        // zz-ZZ matches the regex pattern but is not a real language
        $json = json_encode(['zz-ZZ' => true]);
        $this->validator->validate($this->langHandler, $json);
    }
}
