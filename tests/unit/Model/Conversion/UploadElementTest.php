<?php

namespace unit\Model\Conversion;

use Model\Conversion\UploadElement;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class UploadElementTest extends AbstractTest
{

    #[Test]
    public function constructWithEmptyArray(): void
    {
        $el = new UploadElement();
        $this->assertInstanceOf(UploadElement::class, $el);
    }

    #[Test]
    public function constructWithParams(): void
    {
        $el = new UploadElement(['name' => 'test.txt', 'size' => 100]);

        $this->assertEquals('test.txt', $el->name);
        $this->assertEquals(100, $el->size);
    }

    #[Test]
    public function getMissingPropertyReturnsNull(): void
    {
        $el = new UploadElement();
        $this->assertNull($el->nonexistent);
    }

    #[Test]
    public function issetReturnsTrueForExistingProperty(): void
    {
        $el = new UploadElement(['name' => 'file.doc']);
        $this->assertTrue(isset($el->name));
    }

    #[Test]
    public function issetReturnsFalseForMissingProperty(): void
    {
        $el = new UploadElement();
        $this->assertFalse(isset($el->missing));
    }

    #[Test]
    public function arrayAccessReadWrite(): void
    {
        $el = new UploadElement();
        $el['key'] = 'value';
        $this->assertTrue(isset($el['key']));
        $this->assertEquals('value', $el['key']);
    }

    #[Test]
    public function arrayAccessUnset(): void
    {
        $el = new UploadElement(['tmp_name' => '/tmp/x']);
        unset($el['tmp_name']);
        $this->assertNull($el['tmp_name']);
    }

    #[Test]
    public function dynamicPropertyAssignment(): void
    {
        $el = new UploadElement();
        $el->custom = 'data';
        $this->assertEquals('data', $el->custom);
    }

    #[Test]
    public function getArrayCopyReturnsAllProperties(): void
    {
        $el = new UploadElement(['name' => 'a.txt', 'size' => 50]);
        $copy = $el->getArrayCopy();

        $this->assertIsArray($copy);
        $this->assertEquals('a.txt', $copy['name']);
        $this->assertEquals(50, $copy['size']);
    }

    #[Test]
    public function iterableViaForeach(): void
    {
        $el = new UploadElement();
        $el->file1 = new UploadElement(['name' => 'a.txt']);
        $el->file2 = new UploadElement(['name' => 'b.txt']);

        $names = [];
        foreach ($el as $item) {
            $names[] = $item->name;
        }

        $this->assertCount(2, $names);
        $this->assertContains('a.txt', $names);
        $this->assertContains('b.txt', $names);
    }
}

