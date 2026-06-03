<?php

namespace Matecat\Core\View\API\App\Json\Analysis;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\Constants\StandardMatchTypeNamesConstants;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use Utils\Registry\AppConfig;
use View\API\App\Json\Analysis\AnalysisChunk;
use View\API\App\Json\Analysis\AnalysisFile;
use View\API\App\Json\Analysis\AnalysisJobSummary;

#[CoversClass(AnalysisChunk::class)]
class AnalysisChunkTest extends AbstractTest
{
    private JobStruct $jobStruct;
    private UserStruct $userStruct;
    private StandardMatchTypeNamesConstants $constants;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jobStruct            = new JobStruct();
        $this->jobStruct->id        = null;
        $this->jobStruct->password  = 'abc123';
        $this->jobStruct->status    = 'active';
        $this->jobStruct->source    = 'en-US';
        $this->jobStruct->target    = 'it-IT';
        $this->jobStruct->id_tms    = 1;
        $this->jobStruct->id_mt_engine = 0;
        $this->jobStruct->tm_keys   = '';

        $this->userStruct = new UserStruct();
        $this->constants  = new StandardMatchTypeNamesConstants();

        // Ensure AppConfig::$HTTPHOST is set for URL building
        if (!isset(AppConfig::$HTTPHOST)) {
            AppConfig::$HTTPHOST = 'http://localhost';
        }
    }

    private function makeChunk(): AnalysisChunk
    {
        return new AnalysisChunk($this->jobStruct, 'Test Project', $this->userStruct, $this->constants);
    }

    public function testGetChunkStructReturnsJobStruct(): void
    {
        $chunk = $this->makeChunk();

        $this->assertSame($this->jobStruct, $chunk->getChunkStruct());
    }

    public function testGetPasswordReturnsJobStructPassword(): void
    {
        $chunk = $this->makeChunk();

        $this->assertSame('abc123', $chunk->getPassword());
    }

    public function testGetPasswordReturnsNullWhenPasswordIsNull(): void
    {
        $this->jobStruct->password = null;
        $chunk                     = $this->makeChunk();

        $this->assertNull($chunk->getPassword());
    }

    public function testGetSummaryReturnsAnalysisJobSummary(): void
    {
        $chunk = $this->makeChunk();

        $this->assertInstanceOf(AnalysisJobSummary::class, $chunk->getSummary());
    }

    public function testGetFilesReturnsEmptyArrayInitially(): void
    {
        $chunk = $this->makeChunk();

        $this->assertSame([], $chunk->getFiles());
    }

    public function testHasFileReturnsFalseWhenNoFiles(): void
    {
        $chunk = $this->makeChunk();

        $this->assertFalse($chunk->hasFile(42));
    }

    public function testHasFileReturnsTrueAfterSetFile(): void
    {
        $chunk = $this->makeChunk();
        $file  = $this->makeAnalysisFile(42);

        $chunk->setFile($file);

        $this->assertTrue($chunk->hasFile(42));
    }

    public function testSetFileReturnsSelf(): void
    {
        $chunk = $this->makeChunk();
        $file  = $this->makeAnalysisFile(1);

        $result = $chunk->setFile($file);

        $this->assertSame($chunk, $result);
    }

    public function testSetFileStoresFileById(): void
    {
        $chunk = $this->makeChunk();
        $file  = $this->makeAnalysisFile(99);

        $chunk->setFile($file);
        $files = $chunk->getFiles();

        $this->assertArrayHasKey(99, $files);
        $this->assertSame($file, $files[99]);
    }

    public function testSetMultipleFiles(): void
    {
        $chunk = $this->makeChunk();
        $file1 = $this->makeAnalysisFile(1);
        $file2 = $this->makeAnalysisFile(2);

        $chunk->setFile($file1)->setFile($file2);

        $this->assertCount(2, $chunk->getFiles());
        $this->assertTrue($chunk->hasFile(1));
        $this->assertTrue($chunk->hasFile(2));
    }

    public function testIncrementRaw(): void
    {
        $chunk = $this->makeChunk();
        $chunk->incrementRaw(100);
        $chunk->incrementRaw(50);

        $result = $chunk->jsonSerialize();
        $this->assertSame(150, $result['total_raw']);
    }

    public function testIncrementEquivalent(): void
    {
        $chunk = $this->makeChunk();
        $chunk->incrementEquivalent(10.5);
        $chunk->incrementEquivalent(4.5);

        $result = $chunk->jsonSerialize();
        $this->assertEquals(15, $result['total_equivalent']);
    }

    public function testIncrementIndustry(): void
    {
        $chunk = $this->makeChunk();
        $chunk->incrementIndustry(20.0);
        $chunk->incrementIndustry(5.0);

        $result = $chunk->jsonSerialize();
        // total_industry = max(round(25.0), round(0)) = 25
        $this->assertEquals(25, $result['total_industry']);
    }

    public function testTotalIndustryIsMaxOfIndustryAndEquivalent(): void
    {
        $chunk = $this->makeChunk();
        $chunk->incrementEquivalent(100.0);
        $chunk->incrementIndustry(50.0);

        $result = $chunk->jsonSerialize();
        // max(round(50.0), round(100.0)) = 100
        $this->assertEquals(100, $result['total_industry']);
    }

    /**
     * @throws Exception
     */
    public function testJsonSerializeReturnsExpectedKeys(): void
    {
        $chunk  = $this->makeChunk();
        $result = $chunk->jsonSerialize();

        $this->assertArrayHasKey('password', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('engines', $result);
        $this->assertArrayHasKey('memory_keys', $result);
        $this->assertArrayHasKey('urls', $result);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('total_raw', $result);
        $this->assertArrayHasKey('total_equivalent', $result);
        $this->assertArrayHasKey('total_industry', $result);
    }

    /**
     * @throws Exception
     */
    public function testJsonSerializePasswordAndStatus(): void
    {
        $chunk  = $this->makeChunk();
        $result = $chunk->jsonSerialize();

        $this->assertSame('abc123', $result['password']);
        $this->assertSame('active', $result['status']);
    }

    /**
     * @throws Exception
     */
    public function testJsonSerializeEnginesHasTmAndMtKeys(): void
    {
        $chunk  = $this->makeChunk();
        $result = $chunk->jsonSerialize();

        $this->assertArrayHasKey('tm', $result['engines']);
        $this->assertArrayHasKey('mt', $result['engines']);
    }

    /**
     * @throws Exception
     */
    public function testJsonSerializeMemoryKeysEmptyWhenNoTmKeys(): void
    {
        $chunk  = $this->makeChunk();
        $result = $chunk->jsonSerialize();

        $this->assertSame([], $result['memory_keys']);
    }

    /**
     * @throws Exception
     */
    public function testJsonSerializeFilesAsList(): void
    {
        $chunk = $this->makeChunk();
        $file  = $this->makeAnalysisFile(5);

        $chunk->setFile($file);
        $result = $chunk->jsonSerialize();

        $this->assertCount(1, $result['files']);
        $this->assertSame($file, $result['files'][0]);
    }

    /**
     * @throws Exception
     */
    public function testJsonSerializeSummaryIsAnalysisJobSummary(): void
    {
        $chunk  = $this->makeChunk();
        $result = $chunk->jsonSerialize();

        $this->assertInstanceOf(AnalysisJobSummary::class, $result['summary']);
    }

    /**
     * @throws Exception
     */
    public function testJsonSerializeInitialTotalsAreZero(): void
    {
        $chunk  = $this->makeChunk();
        $result = $chunk->jsonSerialize();

        $this->assertSame(0, $result['total_raw']);
        $this->assertEquals(0, $result['total_equivalent']);
        $this->assertEquals(0, $result['total_industry']);
    }

    public function testHasFileWithStringKey(): void
    {
        $chunk = $this->makeChunk();

        $this->assertFalse($chunk->hasFile('non-existent'));
    }

    private function makeAnalysisFile(int $id): AnalysisFile
    {
        $file = $this->createStub(AnalysisFile::class);
        $file->method('getId')->willReturn($id);

        return $file;
    }
}
