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

    #[Test]
    public function toArray_recursively_converts_nested_elements(): void
    {
        $parent = new UploadElement();
        $parent->file1 = new UploadElement(['name' => 'a.docx', 'size' => 100]);
        $parent->file2 = new UploadElement(['name' => 'b.pdf', 'size' => 200]);

        $result = $parent->toArray();

        $this->assertIsArray($result['file1']);
        $this->assertIsArray($result['file2']);
        $this->assertSame('a.docx', $result['file1']['name']);
        $this->assertSame(100, $result['file1']['size']);
        $this->assertSame('b.pdf', $result['file2']['name']);
        $this->assertSame(200, $result['file2']['size']);
    }

    #[Test]
    public function toArray_with_mask_filters_properties(): void
    {
        $el = new UploadElement(['name' => 'file.txt', 'tmp_name' => '/tmp/abc', 'size' => 512]);

        $result = $el->toArray(['name', 'size']);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('size', $result);
        $this->assertArrayNotHasKey('tmp_name', $result);
    }

    #[Test]
    public function toArray_empty_element_returns_empty_array(): void
    {
        $el = new UploadElement();

        $this->assertSame([], $el->toArray());
    }

    #[Test]
    public function toArray_matches_getUniformGlobalFilesStructure_pattern(): void
    {
        $parent = new UploadElement();
        $child = new UploadElement();
        $child['tmp_name'] = '/tmp/php001';
        $child['name'] = 'document.docx';
        $child['size'] = 4096;
        $child['type'] = 'application/octet-stream';
        $child['error'] = '0';

        $parent->{$child['tmp_name']} = $child;

        $result = $parent->toArray();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('/tmp/php001', $result);
        $this->assertSame('document.docx', $result['/tmp/php001']['name']);
        $this->assertSame(4096, $result['/tmp/php001']['size']);
    }

    #[Test]
    public function get_object_vars_returns_dynamic_properties(): void
    {
        $el = new UploadElement(['name' => 'test.xml', 'tmp_name' => '/tmp/def']);

        $vars = get_object_vars($el);

        $this->assertArrayHasKey('name', $vars);
        $this->assertArrayHasKey('tmp_name', $vars);
        $this->assertSame('test.xml', $vars['name']);
    }
}

