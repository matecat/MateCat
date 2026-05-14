<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\CheckGlossaryResponse;
use Utils\Engines\Results\MyMemory\DeleteGlossaryResponse;
use Utils\Engines\Results\MyMemory\DomainsResponse;
use Utils\Engines\Results\MyMemory\ExportResponse;
use Utils\Engines\Results\MyMemory\FileImportAndStatusResponse;
use Utils\Engines\Results\MyMemory\GetGlossaryResponse;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\KeysGlossaryResponse;
use Utils\Engines\Results\MyMemory\SearchGlossaryResponse;
use Utils\Engines\Results\MyMemory\SetGlossaryResponse;
use Utils\Engines\Results\MyMemory\TagProjectionResponse;
use Utils\Engines\Results\MyMemory\UpdateContributionResponse;
use Utils\Engines\Results\MyMemory\UpdateGlossaryResponse;
use Utils\Registry\AppConfig;

error_reporting(~E_DEPRECATED);

#[Group('PersistenceNeeded')]
#[AllowMockObjectsWithoutExpectations]
class MyMemoryMethodsTest extends AbstractTest
{
    protected EngineStruct $engineStruct;
    protected string $tempFilePath = '';

    public function setUp(): void
    {
        parent::setUp();

        $engineDAO = new EngineDAO(
            Database::obtain(
                AppConfig::$DB_SERVER,
                AppConfig::$DB_USER,
                AppConfig::$DB_PASS,
                AppConfig::$DB_DATABASE
            )
        );

        $engineStructTemplate       = EngineStruct::getStruct();
        $engineStructTemplate->id   = 1;
        $this->engineStruct         = $engineDAO->read($engineStructTemplate)[0];

        $this->tempFilePath = sys_get_temp_dir() . '/mymemory_test_' . uniqid() . '.tmx';
        file_put_contents(
            $this->tempFilePath,
            '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL .
            '<tmx version="1.4"><header/><body></body></tmx>'
        );
    }

    public function tearDown(): void
    {
        if ($this->tempFilePath !== '' && file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
        parent::tearDown();
    }

    private function makeMock(string $jsonReturn): MyMemory
    {
        $mock = @$this->getMockBuilder(MyMemory::class)
            ->setConstructorArgs([$this->engineStruct])
            ->onlyMethods(['_call'])
            ->getMock();

        $mock->expects($this->any())->method('_call')->willReturn($jsonReturn);

        return $mock;
    }

    #[Test]
    public function test_isTMS_returns_true(): void
    {
        $mock = $this->makeMock('{}');
        $this->assertTrue($mock->isTMS());
    }

    #[Test]
    public function test_update_happy_path(): void
    {
        $jsonReturn = json_encode([
            'responseData'   => 'OK',
            'responseStatus' => 200,
            'responseDetails' => '',
            'number_of_results' => 1,
            'segment_ids'    => ['abc-seg-001'],
        ]);

        $mock = $this->makeMock($jsonReturn);

        $config = [
            'segment'        => 'Hello world',
            'translation'    => 'Ciao mondo',
            'newsegment'     => 'Hello beautiful world',
            'newtranslation' => 'Ciao bel mondo',
            'source'         => 'en-US',
            'target'         => 'it-IT',
            'prop'           => '{"project_id":"1","project_name":"test","job_id":"42"}',
            'uid'            => 0,
            'email'          => 'test@matecat.com',
            'set_mt'         => true,
            'spiceMatch'     => false,
        ];

        $result = $mock->update($config);

        $this->assertInstanceOf(UpdateContributionResponse::class, $result);
        $this->assertEquals(200, $result->responseStatus);
        $this->assertEquals('OK', $result->responseData);
        $this->assertIsArray($result->responseDetails);
        $this->assertEquals(1, $result->responseDetails['number_of_results']);
    }

    #[Test]
    public function test_entryStatus_happy_path(): void
    {
        $uuid = 'abc123-entry-uuid';

        $jsonReturn = json_encode([
            'responseData'    => 'OK',
            'responseStatus'  => 200,
            'responseDetails' => '',
        ]);

        $this->engineStruct->others['entry_status_relative_url'] = 'entry/status';
        $mock   = $this->makeMock($jsonReturn);
        $result = $mock->entryStatus($uuid);

        $this->assertInstanceOf(GetMemoryResponse::class, $result);
        $this->assertEquals(200, $result->responseStatus);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_glossaryExport_happy_path(): void
    {
        $exportId   = 'glossary-export-uuid-999';
        $jsonReturn = json_encode([
            'responseData'   => ['id' => $exportId],
            'responseStatus' => 200,
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->glossaryExport(
            'my-key-123',
            'My Glossary',
            'user@example.com',
            'John Doe'
        );

        $this->assertInstanceOf(ExportResponse::class, $result);
        $this->assertEquals(200, $result->responseStatus);
        $this->assertEquals($exportId, $result->id);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_glossaryCheck_happy_path(): void
    {
        $jsonReturn = json_encode([
            'matches' => [],
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->glossaryCheck(
            'Hello',
            'Ciao',
            'en-US',
            'it-IT',
            ['key-001']
        );

        $this->assertInstanceOf(CheckGlossaryResponse::class, $result);
        $this->assertIsArray($result->matches);
        $this->assertEmpty($result->matches);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_glossaryDomains_happy_path(): void
    {
        $jsonReturn = json_encode([
            'entries' => ['general', 'legal'],
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->glossaryDomains(['key-001']);

        $this->assertInstanceOf(DomainsResponse::class, $result);
        $this->assertCount(2, $result->entries);
        $this->assertContains('general', $result->entries);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_glossaryDelete_happy_path(): void
    {
        $jsonReturn = json_encode([
            'responseData'   => 'OK',
            'responseStatus' => 200,
            'responseDetails' => '',
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->glossaryDelete(
            '101',
            '42',
            'secret_pw',
            ['source' => 'Hello', 'target' => 'Ciao', 'language' => 'it-IT']
        );

        $this->assertInstanceOf(DeleteGlossaryResponse::class, $result);
        $this->assertEquals(200, $result->responseStatus);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_glossaryGet_happy_path(): void
    {
        $jsonReturn = json_encode([
            'matches' => [],
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->glossaryGet(
            '42',
            '101',
            'Hello',
            'en-US',
            'it-IT',
            ['key-001']
        );

        $this->assertInstanceOf(GetGlossaryResponse::class, $result);
        $this->assertIsArray($result->matches);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_glossarySearch_happy_path(): void
    {
        $jsonReturn = json_encode([
            'matches' => [],
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->glossarySearch(
            'Hello',
            'en-US',
            'it-IT',
            ['key-001']
        );

        $this->assertInstanceOf(SearchGlossaryResponse::class, $result);
        $this->assertIsArray($result->matches);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_glossaryKeys_happy_path(): void
    {
        $jsonReturn = json_encode([
            'entries' => [],
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->glossaryKeys(
            'en-US',
            'it-IT',
            ['key-001']
        );

        $this->assertInstanceOf(KeysGlossaryResponse::class, $result);
        $this->assertIsArray($result->entries);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_glossarySet_happy_path(): void
    {
        $jsonReturn = json_encode([
            'responseData'   => 'OK',
            'responseStatus' => 200,
            'responseDetails' => '',
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->glossarySet(
            '101',
            '42',
            'secret_pw',
            ['source' => 'Hello', 'target' => 'Ciao', 'language' => 'it-IT']
        );

        $this->assertInstanceOf(SetGlossaryResponse::class, $result);
        $this->assertEquals(200, $result->responseStatus);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_glossaryUpdate_happy_path(): void
    {
        $jsonReturn = json_encode([
            'responseData'   => 'OK',
            'responseStatus' => 200,
            'responseDetails' => '',
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->glossaryUpdate(
            '101',
            '42',
            'secret_pw',
            ['source' => 'Hello', 'target' => 'Ciao Updated', 'language' => 'it-IT']
        );

        $this->assertInstanceOf(UpdateGlossaryResponse::class, $result);
        $this->assertEquals(200, $result->responseStatus);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_importMemory_happy_path(): void
    {
        $importUuid = 'import-uuid-abc456';
        $jsonReturn = json_encode([
            'responseData'    => ['UUID' => $importUuid],
            'responseStatus'  => 202,
            'responseDetails' => '',
        ]);

        $mock = $this->makeMock($jsonReturn);

        $user = new UserStruct();

        $result = $mock->importMemory(
            $this->tempFilePath,
            'a6043e606ac9b5d7ff24',
            $user
        );

        $this->assertInstanceOf(FileImportAndStatusResponse::class, $result);
        $this->assertEquals(202, $result->responseStatus);
        $this->assertEquals($importUuid, $result->id);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_getImportStatus_happy_path(): void
    {
        $statusUuid = 'status-uuid-xyz789';
        $jsonReturn = json_encode([
            'responseData'    => ['UUID' => $statusUuid],
            'responseStatus'  => 200,
            'responseDetails' => '',
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->getImportStatus($statusUuid);

        $this->assertInstanceOf(FileImportAndStatusResponse::class, $result);
        $this->assertEquals(200, $result->responseStatus);
        $this->assertEquals($statusUuid, $result->id);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_emailExport_happy_path(): void
    {
        $exportId   = 'email-export-id-001';
        $jsonReturn = json_encode([
            'responseData'   => ['id' => $exportId],
            'responseStatus' => 200,
        ]);

        $mock = $this->makeMock($jsonReturn);

        $result = $mock->emailExport(
            'tm-key-abcdef',
            'my_memory_export',
            'user@example.com',
            'John',
            'Doe',
            false
        );

        $this->assertInstanceOf(ExportResponse::class, $result);
        $this->assertEquals(200, $result->responseStatus);
        $this->assertEquals($exportId, $result->id);
        $this->assertNull($result->error);
    }

    #[Test]
    public function test_emailExport_throws_exception_on_error_status(): void
    {
        $jsonReturn = json_encode([
            'responseData'   => '',
            'responseStatus' => 500,
            'responseDetails' => 'Export failed',
            'error' => [
                'code'    => -1,
                'message' => 'Export failed due to server error',
            ],
        ]);

        $mock = $this->makeMock($jsonReturn);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(500);

        $mock->emailExport(
            'tm-key-abcdef',
            'my_memory_export',
            'user@example.com',
            'John',
            'Doe',
            false
        );
    }

    #[Test]
    public function test_getTagProjection_whitespace_preserved_around_result(): void
    {
        $innerTranslation = 'Ciao mondo';
        $target           = '  ' . $innerTranslation . '  ';

        $jsonReturn = json_encode([
            'data'           => ['translation' => $innerTranslation],
            'responseStatus' => 200,
        ]);

        $mock   = $this->makeMock($jsonReturn);
        $result = $mock->getTagProjection([
            'source'      => 'Hello world',
            'target'      => $target,
            'suggestion'  => $innerTranslation,
            'source_lang' => 'en-US',
            'target_lang' => 'it-IT',
            'dataRefMap'  => [],
        ]);

        $this->assertInstanceOf(TagProjectionResponse::class, $result);
        $this->assertNotEmpty($result->responseData);
        $this->assertStringContainsString($innerTranslation, (string)$result->responseData);
    }

    #[Test]
    public function test_getTagProjection_without_leading_trailing_whitespace(): void
    {
        $translation = 'Ciao mondo';
        $jsonReturn  = json_encode([
            'data'           => ['translation' => $translation],
            'responseStatus' => 200,
        ]);

        $mock   = $this->makeMock($jsonReturn);
        $result = $mock->getTagProjection([
            'source'      => 'Hello world',
            'target'      => $translation,
            'suggestion'  => $translation,
            'source_lang' => 'en-US',
            'target_lang' => 'it-IT',
            'dataRefMap'  => [],
        ]);

        $this->assertInstanceOf(TagProjectionResponse::class, $result);
        $this->assertStringContainsString($translation, (string)$result->responseData);
    }

    #[Test]
    public function test_getTagProjection_empty_api_response_leaves_responseData_empty(): void
    {
        $jsonReturn = json_encode(['responseStatus' => 200]);

        $mock   = $this->makeMock($jsonReturn);
        $result = $mock->getTagProjection([
            'source'      => 'Hello world',
            'target'      => '  Ciao mondo  ',
            'suggestion'  => 'Ciao mondo',
            'source_lang' => 'en-US',
            'target_lang' => 'it-IT',
            'dataRefMap'  => [],
        ]);

        $this->assertInstanceOf(TagProjectionResponse::class, $result);
        $this->assertSame('', $result->responseData);
    }

    #[Test]
    public function test_getConfigurationParameters_returns_empty_array(): void
    {
        $params = MyMemory::getConfigurationParameters();
        $this->assertIsArray($params);
        $this->assertEmpty($params);
    }
}
