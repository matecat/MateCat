<?php

namespace Matecat\Core\Utils\Tools;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\Constants;
use Utils\Tools\CatUtils;

#[Group('unit')]
class CatUtilsDeleteShaTest extends AbstractTest
{
    private string $workDir;
    private string $sourceFile;
    private string $source = 'test_source';

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'deletesha_' . uniqid();
        mkdir($this->workDir, 0777, true);

        // the file whose sha drives the hash-index filename; its basename is the entry deleteSha() looks for
        $this->sourceFile = $this->workDir . DIRECTORY_SEPARATOR . 'target.txt';
        file_put_contents($this->sourceFile, 'some content');
    }

    protected function tearDown(): void
    {
        $files = glob($this->workDir . DIRECTORY_SEPARATOR . '*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->workDir);

        parent::tearDown();
    }

    /**
     * Replicates the hash-index filename deleteSha() computes internally, so the test
     * writes the index file exactly where the method reads it.
     */
    private function hashFilePath(): string
    {
        $fileSha          = sha1_file($this->sourceFile);
        $segmentationRule = Constants::validateSegmentationRules(null);
        $hashName         = $fileSha . '_' . sha1(($segmentationRule ?? '')) . '|' . $this->source;

        return $this->workDir . DIRECTORY_SEPARATOR . $hashName;
    }

    private function makeCatUtils(): CatUtils
    {
        [$db] = $this->createDatabaseMock();

        return new CatUtils($db);
    }

    #[Test]
    public function deleteShaLeavesTheIndexUntouchedWhenTheFilenameIsNotPresent(): void
    {
        // target.txt is NOT in this list; array_search() returns false → false coerces to key 0
        $hashFile = $this->hashFilePath();
        file_put_contents($hashFile, "other1.txt\nother2.txt\n");

        $this->makeCatUtils()->deleteSha($this->sourceFile, $this->source);

        // BUG: unset($arr[false]) drops the first entry; correct behaviour deletes nothing
        self::assertSame("other1.txt\nother2.txt\n", file_get_contents($hashFile));
    }

    #[Test]
    public function deleteShaRemovesTheMatchingFilenameWhenPresent(): void
    {
        $hashFile = $this->hashFilePath();
        file_put_contents($hashFile, "other1.txt\ntarget.txt\nother2.txt\n");

        $this->makeCatUtils()->deleteSha($this->sourceFile, $this->source);

        self::assertSame("other1.txt\nother2.txt\n", file_get_contents($hashFile));
    }

    #[Test]
    public function deleteShaUnlinksTheIndexWhenItBecomesEmpty(): void
    {
        $hashFile = $this->hashFilePath();
        file_put_contents($hashFile, "target.txt\n");

        $this->makeCatUtils()->deleteSha($this->sourceFile, $this->source);

        self::assertFileDoesNotExist($hashFile);
    }
}
