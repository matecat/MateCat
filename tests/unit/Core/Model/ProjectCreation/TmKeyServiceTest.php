<?php

namespace Matecat\Core\Model\ProjectCreation;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\ProjectCreation\ProjectCreationError;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\TmKeyService;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub as MockStub;
use Utils\Logger\MatecatLogger;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\TMS\TMSFile;
use Utils\TMS\TMSService;

/**
 * Unit tests for TmKeyService — extracted from ProjectManager.
 *
 * Tests cover:
 *  - TM key validation (valid, invalid, exception)
 *  - Key-ring insertion (new vs existing keys, name placeholders)
 *  - TMX upload paths (no key, non-tmx skip, upload exception)
 *  - Error recording into projectStructure
 */
#[AllowMockObjectsWithoutExpectations]
class TmKeyServiceTest extends AbstractTest
{
    private TMSService&MockObject $tmxService;
    private IDatabase&MockStub $dbHandler;
    private MatecatLogger&MockStub $logger;
    private MemoryKeyDao&MockObject $memoryKeyDao;

    /** @var string[] Tracks S3 downloads */
    private array $s3Downloads = [];

    private TestableTmKeyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmxService   = $this->createMock(TMSService::class);
        $this->dbHandler    = $this->createStub(IDatabase::class);
        $this->logger       = $this->createStub(MatecatLogger::class);
        $this->memoryKeyDao = $this->createMock(MemoryKeyDao::class);

        $this->s3Downloads = [];

        $this->service = new TestableTmKeyService(
            $this->tmxService,
            $this->dbHandler,
            $this->logger,
            function (string $fileName): void {
                $this->s3Downloads[] = $fileName;
            },
        );
        $this->service->setMemoryKeyDao($this->memoryKeyDao);
        $this->service->setKeyringOwnerKeys([]); // default: no existing keys
        $this->service->setUserStruct(new UserStruct()); // provide a stub user for pushTMX tests
    }

    // ──────────────────────────────────────────────────────────────
    // setPrivateTMKeys() — key validation
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function testRecordsErrorWhenKeyIsInvalid(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'bad-key', 'name' => ''],
        ]);

        $this->tmxService->method('checkCorrectKey')->willReturn(false);

        $this->service->setPrivateTMKeys($ps, '');

        $errors = $ps->result['errors'];
        self::assertCount(1, $errors);
        self::assertSame(ProjectCreationError::TM_KEY_INVALID->value, $errors[0]['code']);
        self::assertStringContainsString('bad-key', $errors[0]['message']);
    }

    #[Test]
    public function testRecordsErrorWhenKeyCheckReturnsNull(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'null-key', 'name' => ''],
        ]);

        $this->tmxService->method('checkCorrectKey')->willReturn(null);

        $this->service->setPrivateTMKeys($ps, '');

        self::assertCount(1, $ps->result['errors']);
        self::assertSame(ProjectCreationError::TM_KEY_INVALID->value, $ps->result['errors'][0]['code']);
    }

    #[Test]
    public function testRecordsErrorWhenCheckThrowsException(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'throw-key', 'name' => ''],
        ]);

        $this->tmxService->method('checkCorrectKey')
            ->willThrowException(new Exception("API down", -99));

        $this->service->setPrivateTMKeys($ps, '');

        $errors = $ps->result['errors'];
        self::assertCount(1, $errors);
        self::assertSame(-99, $errors[0]['code']);
        self::assertSame("API down", $errors[0]['message']);
    }

    #[Test]
    public function testStopsValidationAtFirstInvalidKey(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'good-key', 'name' => ''],
            ['key' => 'bad-key', 'name' => ''],
        ]);

        $this->tmxService->method('checkCorrectKey')
            ->willReturnCallback(fn(string $key) => $key === 'good-key');

        $this->service->setPrivateTMKeys($ps, '');

        self::assertCount(1, $ps->result['errors']);
        self::assertStringContainsString('bad-key', $ps->result['errors'][0]['message']);
    }

    #[Test]
    public function testDoesNotReachKeyringInsertionOnValidationFailure(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'bad-key', 'name' => ''],
        ]);

        $this->tmxService->method('checkCorrectKey')->willReturn(false);

        // createList should never be called when validation fails
        $this->memoryKeyDao->expects(self::never())->method('createList');

        $this->service->setPrivateTMKeys($ps, '');
    }

    // ──────────────────────────────────────────────────────────────
    // setPrivateTMKeys() — keyring insertion
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function testInsertsNewKeyIntoKeyring(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'new-key', 'name' => 'MyTM'],
        ]);

        $this->tmxService->method('checkCorrectKey')->willReturn(true);

        $this->memoryKeyDao->expects(self::once())
            ->method('createList')
            ->with(self::callback(function (array $list): bool {
                self::assertCount(1, $list);
                /** @var MemoryKeyStruct $item */
                $item = $list[0];
                self::assertSame('new-key', $item->tm_key->key);
                self::assertSame('MyTM', $item->tm_key->name);
                self::assertTrue($item->tm_key->tm);
                self::assertTrue($item->tm_key->glos);
                self::assertSame(42, $item->uid);

                return true;
            }));

        $this->service->setPrivateTMKeys($ps, 'fallback.tmx');

        self::assertEmpty($ps->result['errors']);
    }

    #[Test]
    public function testSkipsExistingKeyInKeyring(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'existing-key', 'name' => 'MyTM'],
        ]);

        $this->tmxService->method('checkCorrectKey')->willReturn(true);

        // Simulate existing key in keyring
        $existingMK = new MemoryKeyStruct();
        $existingTK = new TmKeyStruct();
        $existingTK->key = 'existing-key';
        $existingMK->tm_key = $existingTK;
        $this->service->setKeyringOwnerKeys([$existingMK]);

        // createList should be called with empty array
        $this->memoryKeyDao->expects(self::once())
            ->method('createList')
            ->with([]);

        $this->service->setPrivateTMKeys($ps, '');
    }

    #[Test]
    public function testReplacesProjectIdPlaceholderInKeyName(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'key1', 'name' => 'Project-{{pid}}-TM'],
        ]);

        $this->tmxService->method('checkCorrectKey')->willReturn(true);

        $this->memoryKeyDao->expects(self::once())
            ->method('createList')
            ->with(self::callback(function (array $list): bool {
                self::assertSame('Project-999-TM', $list[0]->tm_key->name);

                return true;
            }));

        $this->service->setPrivateTMKeys($ps, '');
    }

    #[Test]
    public function testUsesFirstTMXFileNameWhenKeyNameEmpty(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'key1', 'name' => ''],
        ]);

        $this->tmxService->method('checkCorrectKey')->willReturn(true);

        $this->memoryKeyDao->expects(self::once())
            ->method('createList')
            ->with(self::callback(function (array $list): bool {
                self::assertSame('my-glossary.tmx', $list[0]->tm_key->name);

                return true;
            }));

        $this->service->setPrivateTMKeys($ps, 'my-glossary.tmx');
    }

    #[Test]
    public function testDoesNotRecordErrorOnCreateListFailure(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'key1', 'name' => 'TM'],
        ]);

        $this->tmxService->method('checkCorrectKey')->willReturn(true);

        // createList throws — should be logged but not added to errors
        $this->memoryKeyDao->method('createList')
            ->willThrowException(new Exception("DB error"));

        $this->service->setPrivateTMKeys($ps, '');

        // No project errors — the exception is just logged
        self::assertEmpty($ps->result['errors']);
    }

    #[Test]
    public function testWithNoKeysSkipsValidationAndInsertsNothing(): void
    {
        $ps = $this->makeProjectStructure([]);

        $this->memoryKeyDao->expects(self::once())
            ->method('createList')
            ->with([]);

        $this->service->setPrivateTMKeys($ps, '');

        self::assertEmpty($ps->result['errors']);
    }

    #[Test]
    public function testValidKeyNoErrorsRecorded(): void
    {
        $ps = $this->makeProjectStructure([
            ['key' => 'valid-key', 'name' => 'TM'],
        ]);

        $this->tmxService->method('checkCorrectKey')->willReturn(true);
        $this->memoryKeyDao->method('createList');

        $this->service->setPrivateTMKeys($ps, '');

        self::assertEmpty($ps->result['errors']);
    }

    // ──────────────────────────────────────────────────────────────
    // pushTMXToMyMemory() — early return paths
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function testPushTMXReturnsEarlyWhenNoPrivateKey(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key'   => [],
            'array_files'      => ['file.xlf'],
            'array_files_meta' => [['extension' => 'xlf']],
            'uid'              => 42,
            'result'           => ['errors' => []],
        ]);

        $this->tmxService->expects(self::never())->method('addTmxInMyMemory');

        $this->service->pushTMXToMyMemory($ps, '/tmp/upload');
    }

    #[Test]
    public function testPushTMXReturnsEarlyWhenPrivateKeyIsEmpty(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key'   => [['key' => '']],
            'array_files'      => ['file.xlf'],
            'array_files_meta' => [['extension' => 'xlf']],
            'uid'              => 42,
            'result'           => ['errors' => []],
        ]);

        $this->tmxService->expects(self::never())->method('addTmxInMyMemory');

        $this->service->pushTMXToMyMemory($ps, '/tmp/upload');
    }

    #[Test]
    public function testPushTMXSkipsNonTmxFiles(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key'   => [['key' => 'abc123']],
            'array_files'      => ['doc.xlf', 'other.txt'],
            'array_files_meta' => [
                ['extension' => 'xlf'],
                ['extension' => 'txt'],
            ],
            'uid'              => 42,
            'result'           => ['errors' => []],
        ]);

        $this->tmxService->expects(self::never())->method('addTmxInMyMemory');

        $this->service->pushTMXToMyMemory($ps, '/tmp/upload');
    }

    #[Test]
    public function testPushTMXRecordsErrorAndRethrowsOnUploadFailure(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key'   => [['key' => 'abc123']],
            'array_files'      => ['my.tmx'],
            'array_files_meta' => [['extension' => 'tmx']],
            'uid'              => 42,
            'result'           => ['errors' => []],
        ]);

        $this->tmxService->method('addTmxInMyMemory')
            ->willThrowException(new Exception("Upload failed", -5));

        $caughtException = null;
        try {
            $this->service->pushTMXToMyMemory($ps, '/tmp/upload');
        } catch (Exception $e) {
            $caughtException = $e;
        }

        self::assertNotNull($caughtException, 'Expected exception to be thrown');
        // Verify error was recorded before re-throw
        self::assertCount(1, $ps->result['errors']);
        self::assertSame(-5, $ps->result['errors'][0]['code']);
    }

    #[Test]
    public function testPushTMXReturnsEarlyWhenArrayFilesIsEmpty(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key'   => [['key' => 'abc123']],
            'array_files'      => [],
            'array_files_meta' => [],
            'uid'              => 42,
            'result'           => ['errors' => []],
        ]);

        $this->tmxService->expects(self::never())->method('addTmxInMyMemory');

        $this->service->pushTMXToMyMemory($ps, '/tmp/upload');

        self::assertEmpty($ps->result['errors']);
    }

    // ──────────────────────────────────────────────────────────────
    // pushTMXToMyMemory() — success path
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function testPushTMXUploadsSuccessfully(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key'   => [['key' => 'abc123']],
            'array_files'      => ['my.tmx'],
            'array_files_meta' => [['extension' => 'tmx']],
            'uid'              => 42,
            'result'           => ['errors' => []],
        ]);

        $this->tmxService->expects(self::once())
            ->method('addTmxInMyMemory');

        $this->service->setSkipLoopForTMXLoadStatus(true);
        $this->service->pushTMXToMyMemory($ps, '/tmp/upload');

        self::assertEmpty($ps->result['errors']);
        // TMX file removed from array_files after upload
        self::assertArrayNotHasKey(0, $ps->array_files);
    }

    // ──────────────────────────────────────────────────────────────
    // loopForTMXLoadStatus() — direct tests
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function testLoopCompletesImmediately(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key'   => [['key' => 'abc123']],
            'array_files'      => [0 => 'my.tmx'],
            'array_files_meta' => [0 => ['extension' => 'tmx']],
            'uid'              => 42,
            'result'           => ['errors' => []],
        ]);

        $this->tmxService->method('tmxUploadStatus')
            ->willReturn(['completed' => true]);

        $file = $this->createStub(TMSFile::class);
        $file->method('getUuid')->willReturn('test-uuid-123');
        $file->method('getName')->willReturn('my.tmx');
        $file->method('getPosition')->willReturn(0);

        $this->service->callLoopForTMXLoadStatus($ps, [$file]);

        self::assertEmpty($ps->result['errors']);
        self::assertArrayNotHasKey(0, $ps->array_files);
    }

    #[Test]
    public function testLoopThrowsOnError(): void
    {
        $ps = new ProjectStructure([
            'private_tm_key'   => [['key' => 'abc123']],
            'array_files'      => [0 => 'my.tmx'],
            'array_files_meta' => [0 => ['extension' => 'tmx']],
            'uid'              => 42,
            'result'           => ['errors' => []],
        ]);

        $this->tmxService->method('tmxUploadStatus')
            ->willThrowException(new Exception('API error', -10));

        $file = $this->createStub(TMSFile::class);
        $file->method('getUuid')->willReturn('test-uuid-123');
        $file->method('getName')->willReturn('my.tmx');
        $file->method('getPosition')->willReturn(0);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API error');

        $this->service->callLoopForTMXLoadStatus($ps, [$file]);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    private function makeProjectStructure(array $privateTmKeys): ProjectStructure
    {
        return new ProjectStructure([
            'id_project'     => 999,
            'uid'            => 42,
            'private_tm_key' => $privateTmKeys,
            'result'         => ['errors' => []],
        ]);
    }
}

/**
 * Testable subclass that allows injection of MemoryKeyDao mock and
 * override of the static getKeyringOwnerKeysByUid call and UserDao.
 */
class TestableTmKeyService extends TmKeyService
{
    private ?MemoryKeyDao $memoryKeyDaoOverride = null;
    private array $keyringOwnerKeys = [];
    private ?UserStruct $userStruct = null;
    private bool $skipLoop = false;

    public function setMemoryKeyDao(MemoryKeyDao $dao): void
    {
        $this->memoryKeyDaoOverride = $dao;
    }

    public function setKeyringOwnerKeys(array $keys): void
    {
        $this->keyringOwnerKeys = $keys;
    }

    public function setUserStruct(?UserStruct $user): void
    {
        $this->userStruct = $user;
    }

    public function setSkipLoopForTMXLoadStatus(bool $skip): void
    {
        $this->skipLoop = $skip;
    }

    public function callLoopForTMXLoadStatus(ProjectStructure $ps, array $files): void
    {
        parent::loopForTMXLoadStatus($ps, $files);
    }

    protected function createMemoryKeyDao(): MemoryKeyDao
    {
        return $this->memoryKeyDaoOverride ?? parent::createMemoryKeyDao();
    }

    protected function getKeyringOwnerKeys(int $uid): array
    {
        return $this->keyringOwnerKeys;
    }

    protected function getUserByUid(int $uid): ?UserStruct
    {
        return $this->userStruct;
    }

    protected function loopForTMXLoadStatus(ProjectStructure $projectStructure, array $memoryFiles): void
    {
        if ($this->skipLoop) {
            foreach ($memoryFiles as $file) {
                unset($projectStructure->array_files[$file->getPosition()]);
                unset($projectStructure->array_files_meta[$file->getPosition()]);
            }
            return;
        }
        parent::loopForTMXLoadStatus($projectStructure, $memoryFiles);
    }

    protected function pollSleep(int $seconds): void
    {
        // no-op in tests
    }
}
