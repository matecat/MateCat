<?php

namespace Matecat\Core\Model\Files;

use Matecat\TestHelpers\AbstractTest;
use Model\Files\FilesJobStruct;
use PHPUnit\Framework\Attributes\Test;

class FilesJobStructTest extends AbstractTest
{
    #[Test]
    public function canSetProperties(): void
    {
        $struct = new FilesJobStruct();
        $struct->id_file = 10;
        $struct->id_job = 20;

        $this->assertSame(10, $struct->id_file);
        $this->assertSame(20, $struct->id_job);
    }

    #[Test]
    public function toArrayReturnsExpectedKeys(): void
    {
        $struct = new FilesJobStruct();
        $struct->id_file = 5;
        $struct->id_job = 15;

        $array = $struct->toArray();

        $this->assertArrayHasKey('id_file', $array);
        $this->assertArrayHasKey('id_job', $array);
        $this->assertSame(5, $array['id_file']);
        $this->assertSame(15, $array['id_job']);
    }
}
