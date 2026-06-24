<?php

namespace Matecat\Core\Model\Files;

use Matecat\TestHelpers\AbstractTest;
use Model\Files\FilesPartsStruct;
use PHPUnit\Framework\Attributes\Test;

class FilesPartsStructTest extends AbstractTest
{
    #[Test]
    public function defaultValuesAreNull(): void
    {
        $struct = new FilesPartsStruct();

        $this->assertNull($struct->id);
        $this->assertNull($struct->id_file);
        $this->assertNull($struct->tag_key);
        $this->assertNull($struct->tag_value);
    }

    #[Test]
    public function canSetProperties(): void
    {
        $struct = new FilesPartsStruct();
        $struct->id = 1;
        $struct->id_file = 10;
        $struct->tag_key = 'chapter';
        $struct->tag_value = 'Chapter 1';

        $this->assertSame(1, $struct->id);
        $this->assertSame(10, $struct->id_file);
        $this->assertSame('chapter', $struct->tag_key);
        $this->assertSame('Chapter 1', $struct->tag_value);
    }

    #[Test]
    public function toArrayReturnsExpectedKeys(): void
    {
        $struct = new FilesPartsStruct();
        $struct->id = 3;
        $struct->id_file = 7;
        $struct->tag_key = 'section';
        $struct->tag_value = 'intro';

        $array = $struct->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('id_file', $array);
        $this->assertArrayHasKey('tag_key', $array);
        $this->assertArrayHasKey('tag_value', $array);
    }
}
