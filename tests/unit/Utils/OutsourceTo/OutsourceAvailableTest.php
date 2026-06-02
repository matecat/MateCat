<?php

namespace unit\Utils\OutsourceTo;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\OutsourceTo\OutsourceAvailable;

class OutsourceAvailableTest extends AbstractTest
{
    #[Test]
    public function returnsFalseForNonArray(): void
    {
        $this->assertFalse(OutsourceAvailable::isOutsourceAvailable('string'));
        $this->assertFalse(OutsourceAvailable::isOutsourceAvailable(null));
        $this->assertFalse(OutsourceAvailable::isOutsourceAvailable(42));
    }

    #[Test]
    public function returnsTrueWhenAllFlagsAreFalse(): void
    {
        $info = [
            'custom_payable_rate' => false,
            'disabled_email' => false,
            'language_not_supported' => false,
        ];

        $this->assertTrue(OutsourceAvailable::isOutsourceAvailable($info));
    }

    #[Test]
    public function returnsTrueForEmptyArray(): void
    {
        $this->assertTrue(OutsourceAvailable::isOutsourceAvailable([]));
    }

    #[Test]
    public function returnsFalseWhenCustomPayableRateIsTrue(): void
    {
        $this->assertFalse(OutsourceAvailable::isOutsourceAvailable(['custom_payable_rate' => true]));
    }

    #[Test]
    public function returnsFalseWhenDisabledEmailIsTrue(): void
    {
        $this->assertFalse(OutsourceAvailable::isOutsourceAvailable(['disabled_email' => true]));
    }

    #[Test]
    public function returnsFalseWhenLanguageNotSupportedIsTrue(): void
    {
        $this->assertFalse(OutsourceAvailable::isOutsourceAvailable(['language_not_supported' => true]));
    }

    #[Test]
    public function returnsFalseWhenMultipleFlagsAreTrue(): void
    {
        $info = [
            'custom_payable_rate' => true,
            'disabled_email' => true,
            'language_not_supported' => false,
        ];

        $this->assertFalse(OutsourceAvailable::isOutsourceAvailable($info));
    }
}
