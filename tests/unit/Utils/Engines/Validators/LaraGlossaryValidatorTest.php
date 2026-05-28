<?php

namespace unit\Utils\Engines\Validators;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Engines\Validators\LaraGlossaryValidator;

class LaraGlossaryValidatorTest extends AbstractTest
{
    #[Test]
    public function test_validate_returns_object_for_valid_json_array_of_strings()
    {
        $obj = new EngineValidatorObject();
        $obj->glossaryString = json_encode(['uuid-1', 'uuid-2', 'uuid-3']);

        $validator = new LaraGlossaryValidator();
        $result = $validator->validate($obj);

        $this->assertSame($obj, $result);
    }

    #[Test]
    public function test_validate_returns_object_for_empty_json_array()
    {
        $obj = new EngineValidatorObject();
        $obj->glossaryString = '[]';

        $validator = new LaraGlossaryValidator();
        $result = $validator->validate($obj);

        $this->assertSame($obj, $result);
    }

    #[Test]
    public function test_validate_throws_exception_for_invalid_json_string()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('lara_glossaries is not a valid JSON');

        $obj = new EngineValidatorObject();
        $obj->glossaryString = 'not valid json {[}';

        $validator = new LaraGlossaryValidator();
        $validator->validate($obj);
    }

    #[Test]
    public function test_validate_throws_exception_for_non_array_json_string()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('lara_glossaries is not a valid JSON');

        $obj = new EngineValidatorObject();
        $obj->glossaryString = '"hello"';

        $validator = new LaraGlossaryValidator();
        $validator->validate($obj);
    }

    #[Test]
    public function test_validate_throws_exception_for_non_array_json_number()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('lara_glossaries is not a valid JSON');

        $obj = new EngineValidatorObject();
        $obj->glossaryString = '123';

        $validator = new LaraGlossaryValidator();
        $validator->validate($obj);
    }

    #[Test]
    public function test_validate_throws_exception_for_null_json()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('lara_glossaries is not a valid JSON');

        $obj = new EngineValidatorObject();
        $obj->glossaryString = 'null';

        $validator = new LaraGlossaryValidator();
        $validator->validate($obj);
    }

    #[Test]
    public function test_validate_throws_exception_for_array_with_integer_element()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('non string value');

        $obj = new EngineValidatorObject();
        $obj->glossaryString = json_encode([42]);

        $validator = new LaraGlossaryValidator();
        $validator->validate($obj);
    }

    #[Test]
    public function test_validate_throws_exception_for_mixed_array_with_invalid_element()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('non string value');

        $obj = new EngineValidatorObject();
        $obj->glossaryString = json_encode(['valid-string', 99, 'another-valid-string']);

        $validator = new LaraGlossaryValidator();
        $validator->validate($obj);
    }

    #[Test]
    public function test_validate_returns_object_for_single_element_string_array()
    {
        $obj = new EngineValidatorObject();
        $obj->glossaryString = json_encode(['only-one-uuid']);

        $validator = new LaraGlossaryValidator();
        $result = $validator->validate($obj);

        $this->assertSame($obj, $result);
    }
}
