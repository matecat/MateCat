<?php

namespace Matecat\Core\Model\Files;

use Matecat\TestHelpers\AbstractTest;
use Model\Files\FileStruct;
use PHPUnit\Framework\Attributes\Test;

class FileStructTest extends AbstractTest
{
    #[Test]
    public function canInstantiateWithProperties(): void
    {
        $struct = new FileStruct();
        $struct->id = 1;
        $struct->id_project = 10;
        $struct->filename = 'test.xliff';
        $struct->source_language = 'en-US';
        $struct->mime_type = 'application/xliff+xml';
        $struct->sha1_original_file = 'abc123';
        $struct->is_converted = true;

        $this->assertSame(1, $struct->id);
        $this->assertSame(10, $struct->id_project);
        $this->assertSame('test.xliff', $struct->filename);
        $this->assertSame('en-US', $struct->source_language);
        $this->assertSame('application/xliff+xml', $struct->mime_type);
        $this->assertSame('abc123', $struct->sha1_original_file);
        $this->assertTrue($struct->is_converted);
    }

    #[Test]
    public function toArrayReturnsExpectedKeys(): void
    {
        $struct = new FileStruct();
        $struct->id = 5;
        $struct->id_project = 20;
        $struct->filename = 'doc.pdf';
        $struct->source_language = 'it-IT';
        $struct->mime_type = 'application/pdf';
        $struct->sha1_original_file = 'def456';
        $struct->is_converted = false;

        $array = $struct->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('id_project', $array);
        $this->assertArrayHasKey('filename', $array);
        $this->assertSame(5, $array['id']);
    }
}
