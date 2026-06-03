<?php

namespace Matecat\Core\Model\Warnings;

use Matecat\TestHelpers\AbstractTest;
use Model\Warnings\GlobalWarningStruct;
use PHPUnit\Framework\Attributes\Test;

class GlobalWarningStructTest extends AbstractTest
{
    #[Test]
    public function canSetProperties(): void
    {
        $struct = new GlobalWarningStruct();
        $struct->id_segment = '100';
        $struct->serialized_errors_list = '["error1","error2"]';

        $this->assertSame('100', $struct->id_segment);
        $this->assertSame('["error1","error2"]', $struct->serialized_errors_list);
    }

    #[Test]
    public function toArrayReturnsExpectedKeys(): void
    {
        $struct = new GlobalWarningStruct();
        $struct->id_segment = '50';
        $struct->serialized_errors_list = '[]';

        $array = $struct->toArray();

        $this->assertArrayHasKey('id_segment', $array);
        $this->assertArrayHasKey('serialized_errors_list', $array);
    }

    #[Test]
    public function arrayAccessWorks(): void
    {
        $struct = new GlobalWarningStruct();
        $struct['id_segment'] = '200';
        $struct['serialized_errors_list'] = '["warn"]';

        $this->assertSame('200', $struct['id_segment']);
        $this->assertSame('["warn"]', $struct['serialized_errors_list']);
        $this->assertTrue(isset($struct['id_segment']));
    }

    #[Test]
    public function arrayAccessOffsetExistsReturnsFalseForUnknown(): void
    {
        $struct = new GlobalWarningStruct();

        $this->assertFalse(isset($struct['nonexistent']));
    }
}
