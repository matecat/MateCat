<?php

namespace Matecat\Core\View\API\Commons;

use Aws\CommandInterface;
use Aws\S3\Exception\S3Exception;
use DomainException;
use Exception;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Matecat\SimpleS3\Client as S3Client;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\UnknownPropertyException;
use Model\FilesStorage\S3FilesStorage;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Utils\Registry\AppConfig;
use View\API\Commons\ZipContentObject;
use ZipArchive;

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

    /**
     * getContent() returns what it reads from the disk without keeping it: document_content stays
     * empty, so the file is still seen as on the disk.
     */
    #[Test]
    public function getContentDoesNotKeepTheFileItReads(): void
    {
        $tmp = (string)tempnam(sys_get_temp_dir(), 'zip_test_');
        file_put_contents($tmp, 'file-content');

        try {
            $obj = new ZipContentObject(['output_filename' => 'out.xlf', 'input_filename' => $tmp]);

            $this->assertSame('file-content', $obj->getContent());
            $this->assertNull($obj->document_content);
            $this->assertSame($tmp, $obj->localPath());
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * A file on the disk is added by path; an office document or an archive is stored as it is,
     * any other file is deflated.
     */
    #[Test]
    public function addToZipStoresACompressedFileAndDeflatesTheOthers(): void
    {
        $dir = sys_get_temp_dir() . '/zip_test_' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/a.target', str_repeat('docx-bytes ', 100));
        file_put_contents($dir . '/b.target', str_repeat('xliff-bytes ', 100));
        // A file made by tempnam(): its mode must not reach the archive.
        chmod($dir . '/a.target', 0600);
        $zipPath = $dir . '/out.zip';

        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE));
            (new ZipContentObject(['output_filename' => 'a.docx', 'input_filename' => $dir . '/a.target']))->addToZip($zip, 'A.DOCX');
            (new ZipContentObject(['output_filename' => 'b.xlf', 'input_filename' => $dir . '/b.target']))->addToZip($zip, 'b.xlf');
            $this->assertTrue($zip->close());

            $this->assertTrue($zip->open($zipPath));
            $this->assertSame(ZipArchive::CM_STORE, $zip->statName('A.DOCX')['comp_method'] ?? null);
            $this->assertSame(ZipArchive::CM_DEFLATE, $zip->statName('b.xlf')['comp_method'] ?? null);
            $this->assertSame(str_repeat('docx-bytes ', 100), $zip->getFromName('A.DOCX'));
            // A regular file with mode 0666, as addFromString() writes it.
            $this->assertTrue($zip->getExternalAttributesName('A.DOCX', $opsys, $attributes));
            $this->assertSame(ZipArchive::OPSYS_UNIX, $opsys);
            $this->assertSame(0100666, $attributes >> 16);
            $zip->close();
        } finally {
            @unlink($dir . '/a.target');
            @unlink($dir . '/b.target');
            @unlink($zipPath);
            @rmdir($dir);
        }
    }

    #[Test]
    public function addToZipAddsAFileNotOnDiskFromItsContent(): void
    {
        $zipPath = sys_get_temp_dir() . '/zip_test_' . uniqid() . '.zip';

        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE));
            (new ZipContentObject(['output_filename' => 'a.txt', 'document_content' => 'in-memory']))->addToZip($zip, 'a.txt');
            $this->assertTrue($zip->close());

            $this->assertTrue($zip->open($zipPath));
            $this->assertSame('in-memory', $zip->getFromName('a.txt'));
            $zip->close();
        } finally {
            @unlink($zipPath);
        }
    }

    /**
     * On S3 a file that is not on the local disk is read from the bucket: a missing key fails.
     */
    #[Test]
    public function getContentFailsWhenTheFileIsNotInTheS3Bucket(): void
    {
        $storageMethod = AppConfig::$FILE_STORAGE_METHOD;
        AppConfig::$FILE_STORAGE_METHOD = 's3';
        $configured = S3FilesStorage::getStaticS3Client();
        $client     = new ReflectionProperty(S3FilesStorage::class, 'CLIENT');
        $client->setValue(null, new S3Client([
            'version'     => 'latest',
            'region'      => 'eu-central-1',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'handler'     => static fn (CommandInterface $command) => Create::rejectionFor(
                new S3Exception('Not found', $command, ['response' => new Psr7Response(404)])
            ),
        ]));

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('File: files/20261110/missing.docx is not present in S3 storage bucket.');

            (new ZipContentObject(['output_filename' => 'a.docx', 'input_filename' => 'files/20261110/missing.docx']))->getContent();
        } finally {
            $client->setValue(null, $configured);
            AppConfig::$FILE_STORAGE_METHOD = $storageMethod;
        }
    }
}
