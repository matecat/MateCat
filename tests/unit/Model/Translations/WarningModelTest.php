<?php

namespace unit\Model\Translations;

use Model\Translations\WarningModel;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class WarningModelTest extends AbstractTest
{
    #[Test]
    public function constantsArePowersOfTwo(): void
    {
        $this->assertSame(1, WarningModel::ERROR);
        $this->assertSame(2, WarningModel::WARNING);
        $this->assertSame(4, WarningModel::NOTICE);
        $this->assertSame(8, WarningModel::INFO);
        $this->assertSame(16, WarningModel::DEBUG);
    }

    #[Test]
    public function constantsCanBeCombinedAsBitmask(): void
    {
        $combined = WarningModel::ERROR | WarningModel::WARNING;
        $this->assertSame(3, $combined);
        $this->assertTrue(($combined & WarningModel::ERROR) === WarningModel::ERROR);
        $this->assertTrue(($combined & WarningModel::WARNING) === WarningModel::WARNING);
        $this->assertFalse(($combined & WarningModel::NOTICE) === WarningModel::NOTICE);
    }
}
