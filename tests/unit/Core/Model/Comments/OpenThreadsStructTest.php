<?php

namespace Matecat\Core\Model\Comments;

use Matecat\TestHelpers\AbstractTest;
use Model\Comments\OpenThreadsStruct;
use PHPUnit\Framework\Attributes\Test;

class OpenThreadsStructTest extends AbstractTest
{
    #[Test]
    public function canSetProperties(): void
    {
        $struct = new OpenThreadsStruct();
        $struct->id_project = 1;
        $struct->password = 'secret';
        $struct->id_job = 10;
        $struct->count = 5;

        $this->assertSame(1, $struct->id_project);
        $this->assertSame('secret', $struct->password);
        $this->assertSame(10, $struct->id_job);
        $this->assertSame(5, $struct->count);
    }

    #[Test]
    public function toArrayReturnsExpectedKeys(): void
    {
        $struct = new OpenThreadsStruct();
        $struct->id_project = 1;
        $struct->password = 'abc';
        $struct->id_job = 10;
        $struct->count = 3;

        $array = $struct->toArray();

        $this->assertArrayHasKey('id_project', $array);
        $this->assertArrayHasKey('password', $array);
        $this->assertArrayHasKey('id_job', $array);
        $this->assertArrayHasKey('count', $array);
    }
}
