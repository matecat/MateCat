<?php

namespace unit\LQA;

use Matecat\ICU\MessagePatternValidator;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\ICUSourceSegmentDetector;

class ICUSourceSegmentDetectorTest extends AbstractTest
{
    #[Test]
    public function returnsTrueForValidIcuWhenEnabled(): void
    {
        $validator = new MessagePatternValidator('en-US', '{count, plural, one{# item} other{# items}}');

        $this->assertTrue(
            ICUSourceSegmentDetector::sourceContainsIcu($validator, true)
        );
    }

    #[Test]
    public function returnsFalseWhenDisabled(): void
    {
        $validator = new MessagePatternValidator('en-US', '{count, plural, one{# item} other{# items}}');

        $this->assertFalse(
            ICUSourceSegmentDetector::sourceContainsIcu($validator, false)
        );
    }

    #[Test]
    public function returnsFalseForPlainTextWhenEnabled(): void
    {
        $validator = new MessagePatternValidator('en-US', 'Hello World');

        $this->assertFalse(
            ICUSourceSegmentDetector::sourceContainsIcu($validator, true)
        );
    }

    #[Test]
    public function returnsFalseForSimpleArgumentWhenEnabled(): void
    {
        // Simple named argument {name} is valid ICU but NOT complex syntax
        // (no plural/select/selectordinal keywords)
        $validator = new MessagePatternValidator('en-US', 'Hello {name}, welcome!');

        $this->assertFalse(
            ICUSourceSegmentDetector::sourceContainsIcu($validator, true)
        );
    }
}
