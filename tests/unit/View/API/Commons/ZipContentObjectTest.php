<?php

namespace unit\View\API\Commons;

use DomainException;
use Exception;
use Model\DataAccess\UnknownPropertyException;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use View\API\Commons\ZipContentObject;

class ZipContentObjectTest extends AbstractTest
{
    #[Test]
    public function constructorWithAssociativeArraySetsProperties(): void
    {
        $obj = new ZipContentObject([
            'output_filename'  => 'out.xlf',
            'input_filename'   => '/tmp/in.xlf',
            'document_content' => '<xliff/>',
        ]);

        $this->assertSame('out.xlf', $obj->output_filename);
        $this->assertSame('/tmp/in.xlf', $obj->input_filename);
        $this->assertSame('<xliff/>', $obj->document_content);
    }

    #[Test]
    public function constructorWithEmptyArrayLeavesDefaults(): void
    {
        $obj = new ZipContentObject([]);

        $this->assertNull($obj->input_filename);
        $this->assertNull($obj->document_content);
    }

    #[Test]
    public function constructorWithMultidimensionalArrayBuildsMultipleTimes(): void
    {
        $obj = new ZipContentObject([
            ['output_filename' => 'first.xlf'],
            ['output_filename' => 'second.xlf'],
        ]);

        $this->assertSame('second.xlf', $obj->output_filename);
    }

    #[Test]
    public function constructorWithZipContentObjectCopiesProperties(): void
    {
        $source = new ZipContentObject([
            'output_filename'  => 'copy.xlf',
            'document_content' => 'content',
        ]);

        $copy = new ZipContentObject($source);

        $this->assertSame('copy.xlf', $copy->output_filename);
        $this->assertSame('content', $copy->document_content);
    }

    #[Test]
    public function buildWithAssociativeArraySetsProperties(): void
    {
        $obj = new ZipContentObject(['output_filename' => 'initial.xlf']);
        $obj->build(['output_filename' => 'updated.xlf']);

        $this->assertSame('updated.xlf', $obj->output_filename);
    }

    #[Test]
    public function buildWithMultidimensionalArrayRecurses(): void
    {
        $obj = new ZipContentObject(['output_filename' => 'initial.xlf']);
        $obj->build([
            ['output_filename' => 'a.xlf'],
            ['output_filename' => 'b.xlf'],
        ]);

        $this->assertSame('b.xlf', $obj->output_filename);
    }

    #[Test]
    public function buildWithEmptyArrayDoesNothing(): void
    {
        $obj = new ZipContentObject(['output_filename' => 'keep.xlf']);
        $obj->build([]);

        $this->assertSame('keep.xlf', $obj->output_filename);
    }

    #[Test]
    public function setUnknownPropertyThrows(): void
    {
        $this->expectException(UnknownPropertyException::class);
        $this->expectExceptionMessageMatches('/Unknown property/');

        $obj = new ZipContentObject(['output_filename' => 'test.xlf']);
        $obj->nonexistent = 'value';
    }

    #[Test]
    public function constructorWithUnknownPropertyThrows(): void
    {
        $this->expectException(DomainException::class);

        new ZipContentObject(['output_filename' => 'test.xlf', 'bogus' => 'val']);
    }

    #[Test]
    public function toArrayReturnsPublicProperties(): void
    {
        $obj = new ZipContentObject([
            'output_filename'  => 'out.xlf',
            'input_filename'   => '/tmp/in.xlf',
            'document_content' => '<xliff/>',
        ]);

        $arr = $obj->toArray();

        $this->assertSame('out.xlf', $arr['output_filename']);
        $this->assertSame('/tmp/in.xlf', $arr['input_filename']);
        $this->assertSame('<xliff/>', $arr['document_content']);
    }

    #[Test]
    public function getContentReturnsDocumentContentWhenSet(): void
    {
        $obj = new ZipContentObject([
            'output_filename'  => 'out.xlf',
            'document_content' => '<xliff>data</xliff>',
        ]);

        $this->assertSame('<xliff>data</xliff>', $obj->getContent());
    }

    #[Test]
    public function getContentReadsFromFilesystem(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'zip_test_');
        file_put_contents($tmp, 'file-content');

        try {
            $obj = new ZipContentObject([
                'output_filename' => 'out.xlf',
                'input_filename'  => $tmp,
            ]);

            $content = $obj->getContent();
            $this->assertSame('file-content', $content);
        } finally {
            @unlink($tmp);
        }
    }

    #[Test]
    public function getContentThrowsWhenInputIsNotAFile(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Error while retrieving input_filename content/');

        $obj = new ZipContentObject([
            'output_filename' => 'out.xlf',
            'input_filename'  => sys_get_temp_dir(),
        ]);

        $obj->getContent();
    }

    #[Test]
    public function getContentReturnsNullWhenNothingSet(): void
    {
        $obj = new ZipContentObject(['output_filename' => 'out.xlf']);

        $this->assertNull($obj->getContent());
    }
}
