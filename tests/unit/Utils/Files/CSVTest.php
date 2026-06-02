<?php

namespace unit\Utils\Files;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Files\CSV;

class CSVTest extends AbstractTest
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/csv_test_' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        rmdir($this->tmpDir);
        parent::tearDown();
    }

    #[Test]
    public function headersReturnsFirstRow(): void
    {
        $path = $this->tmpDir . '/test.csv';
        file_put_contents($path, "name,age,city\nJohn,30,Rome\n");

        $headers = CSV::headers($path);

        $this->assertSame(['name', 'age', 'city'], $headers);
    }

    #[Test]
    public function headersReturnsNullForNonexistentFile(): void
    {
        $result = @CSV::headers($this->tmpDir . '/nonexistent.csv');

        $this->assertNull($result);
    }

    #[Test]
    public function parseToArrayReturnsAllRows(): void
    {
        $path = $this->tmpDir . '/test.csv';
        file_put_contents($path, "a,b,c\n1,2,3\n4,5,6\n");

        $data = CSV::parseToArray($path);

        $this->assertCount(3, $data);
        $this->assertSame(['a', 'b', 'c'], $data[0]);
        $this->assertSame(['1', '2', '3'], $data[1]);
        $this->assertSame(['4', '5', '6'], $data[2]);
    }

    #[Test]
    public function parseToArrayWithCustomDelimiter(): void
    {
        $path = $this->tmpDir . '/test.tsv';
        file_put_contents($path, "a\tb\tc\n1\t2\t3\n");

        $data = CSV::parseToArray($path, "\t");

        $this->assertCount(2, $data);
        $this->assertSame(['a', 'b', 'c'], $data[0]);
    }

    #[Test]
    public function parseToArrayReturnsEmptyForNonexistentFile(): void
    {
        $data = @CSV::parseToArray($this->tmpDir . '/nope.csv');

        $this->assertSame([], $data);
    }

    #[Test]
    public function saveWritesCsvFile(): void
    {
        $path = $this->tmpDir . '/output.csv';
        $data = [
            ['name', 'age'],
            ['Alice', '25'],
            ['Bob', '30'],
        ];

        $result = CSV::save($path, $data);

        $this->assertTrue($result);
        $this->assertFileExists($path);

        $parsed = CSV::parseToArray($path);
        $this->assertCount(3, $parsed);
        $this->assertSame('name', $parsed[0][0]);
        $this->assertSame('Alice', $parsed[1][0]);
    }

    #[Test]
    public function saveWithEmptyDataCreatesEmptyFile(): void
    {
        $path = $this->tmpDir . '/empty.csv';

        $result = CSV::save($path);

        $this->assertTrue($result);
        $this->assertFileExists($path);
        $this->assertSame('', file_get_contents($path));
    }

    #[Test]
    public function extractReturnsFalseWhenFilePathNotSet(): void
    {
        $uploadElement = new \Model\Conversion\UploadElement();

        $result = CSV::extract($uploadElement);

        $this->assertFalse($result);
    }

    #[Test]
    public function extractConvertsToCsvAndReturnsPath(): void
    {
        $path = $this->tmpDir . '/input.csv';
        file_put_contents($path, "col1,col2\nval1,val2\n");

        $uploadElement = new \Model\Conversion\UploadElement();
        $uploadElement->file_path = $path;

        $result = CSV::extract($uploadElement, 'test_');

        $this->assertIsString($result);
        $this->assertFileExists($result);
        $this->assertSame($result, $uploadElement->file_path);
        $this->assertFalse(file_exists($path));

        unlink($result);
    }
}
