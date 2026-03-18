<?php

namespace unit\Utils;

use Exception;
use Matecat\SubFiltering\Enum\InjectableFiltersTags;
use Matecat\SubFiltering\HandlersSorter;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use TypeError;
use Utils\Subfiltering\SubfilteringOptionsValidator;

class SubfilteringOptionsValidatorTest extends AbstractTest
{
    #[Test]
    public function test_validate_returns_null_when_subfiltering_is_disabled()
    {
        $result = SubfilteringOptionsValidator::validate('none');

        $this->assertEquals("null", $result);
    }

    #[Test]
    public function test_validate_returns_empty_array_when_default_handlers_are_provided()
    {
        $defaultHandlers = InjectableFiltersTags::tagNamesForArrayClasses(
            array_keys(HandlersSorter::getDefaultInjectedHandlers())
        );

        $jsonHandlers = json_encode($defaultHandlers);
        $result = SubfilteringOptionsValidator::validate($jsonHandlers);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function test_validate_returns_array_for_custom_handlers()
    {
        $customHandlers = json_encode(['markup']);
        $result = SubfilteringOptionsValidator::validate($customHandlers);

        $this->assertIsArray($result);
        $this->assertEquals(['markup'], $result);
    }

    #[Test]
    public function test_validate_with_multiple_custom_handlers()
    {
        $customHandlers = json_encode(['markup', 'sprintf']);
        $result = SubfilteringOptionsValidator::validate($customHandlers);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContains('markup', $result);
        $this->assertContains('sprintf', $result);
    }

    #[Test]
    public function test_validate_with_empty_array()
    {
        $result = SubfilteringOptionsValidator::validate('[]');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function test_validate_throws_exception_for_invalid_json()
    {
        $this->expectException(Exception::class);

        SubfilteringOptionsValidator::validate('["invalid json string"]');
    }

    #[Test]
    public function test_validate_throws_exception_for_non_array_json()
    {
        $this->expectException(TypeError::class);

        SubfilteringOptionsValidator::validate('"string"');
    }

    #[Test]
    public function test_validate_with_null_json_string()
    {
        $result = SubfilteringOptionsValidator::validate('null');

        $this->assertEquals("null", $result);
    }

    #[Test]
    public function test_validate_with_subset_of_default_handlers()
    {
        $defaultHandlers = InjectableFiltersTags::tagNamesForArrayClasses(
            array_keys(HandlersSorter::getDefaultInjectedHandlers())
        );

        // Take only first handler from defaults (subset)
        $subsetHandlers = json_encode(array_slice($defaultHandlers, 0, 1));
        $result = SubfilteringOptionsValidator::validate($subsetHandlers);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function test_validate_with_handlers_in_different_order()
    {
        $defaultHandlers = InjectableFiltersTags::tagNamesForArrayClasses(
            array_keys(HandlersSorter::getDefaultInjectedHandlers())
        );

        // Reverse the order
        $reversedHandlers = json_encode(array_reverse($defaultHandlers));
        $result = SubfilteringOptionsValidator::validate($reversedHandlers);

        // Should return empty array since it contains all default handlers
        $this->assertEquals([], $result);
    }

    #[Test]
    public function test_validate_preserves_handler_order()
    {
        $handlers = ['sprintf', 'markup', 'twig'];
        $jsonHandlers = json_encode($handlers);
        $result = SubfilteringOptionsValidator::validate($jsonHandlers);

        $this->assertEquals($handlers, $result);
    }

    #[Test]
    public function test_validate_with_single_handler()
    {
        $result = SubfilteringOptionsValidator::validate('["twig"]');

        $this->assertIsArray($result);
        $this->assertEquals(['twig'], $result);
    }
}
