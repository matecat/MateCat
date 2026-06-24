<?php

namespace Matecat\Core\Model\ConnectedServices\GDrive;

use Exception;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_Permission;
use Google_Service_Drive_Resource_Permissions;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\ConnectedServices\GDrive\RemoteFileService;
use Model\ConnectedServices\GDrive\Session;
use Model\FilesStorage\AbstractFilesStorage;
use Model\Filters\FiltersConfigTemplateStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;

class TestableSession extends Session
{
    /** @var array<string, mixed>|null */
    public ?array $lastConversionHash = null;

    public ?Exception $conversionException = null;

    /** @var array<string, mixed>|null */
    public ?array $forcedToken = null;

    public ?Google_Service_Drive $mockService = null;

    public function doConversion(string $file_name): array
    {
        if ($this->conversionException !== null) {
            throw $this->conversionException;
        }

        if ($this->lastConversionHash !== null) {
            return $this->lastConversionHash;
        }

        return parent::doConversion($file_name);
    }

    public function getTokenByUser(UserStruct $user): ?array
    {
        if ($this->forcedToken !== null) {
            $this->serviceStruct = new ConnectedServiceStruct();
            $this->serviceStruct->id = 1;

            return $this->forcedToken;
        }

        return parent::getTokenByUser($user);
    }

    public function getService(Google_Client $gClient): ?Google_Service_Drive
    {
        if ($this->mockService !== null) {
            $this->serviceStruct = $this->serviceStruct ?? new ConnectedServiceStruct([
                'id' => 1,
                'uid' => 42,
                'service' => 'gdrive',
                'email' => 'test@example.com',
                'name' => 'Test',
                'created_at' => '2024-01-01 00:00:00',
            ]);
            return $this->mockService;
        }

        return parent::getService($gClient);
    }
}

class SessionTest extends AbstractTest
{
    private function createUserStruct(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';
        $user->first_name = 'Test';
        $user->last_name = 'User';

        return $user;
    }

    private function createSessionData(): array
    {
        return [
            'uid' => 42,
            'user' => $this->createUserStruct(),
            'upload_token' => 'test-upload-token',
            'actualSourceLang' => 'en-US',
        ];
    }

    #[Test]
    public function constructorWithNoSessionReturnsEarly(): void
    {
        $sessionData = [];
        $session = new Session($sessionData);

        $this->assertFalse($session->hasFiles());
    }

    #[Test]
    public function constructorWithSessionDataSetsProperties(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $this->assertFalse($session->hasFiles());
    }

    #[Test]
    public function constructorWithExistingGdriveSessionRetainsIt(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [
                'file1' => [Session::FILE_NAME => 'test.docx'],
            ],
        ];

        $session = new Session($sessionData);
        $this->assertTrue($session->hasFiles());
    }

    #[Test]
    public function constructorWithNonArrayGdriveSessionInitializesEmpty(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = 'not-an-array';

        $session = new Session($sessionData);
        $this->assertFalse($session->hasFiles());
    }

    #[Test]
    public function constructorWithInjectedFilesStorage(): void
    {
        $sessionData = $this->createSessionData();
        $filesStorage = $this->createStub(AbstractFilesStorage::class);

        $session = new Session($sessionData, null, $filesStorage);

        $this->assertFalse($session->hasFiles());
    }

    #[Test]
    public function getInstanceForCLICreatesSession(): void
    {
        $sessionData = $this->createSessionData();
        $session = TestableSession::getInstanceForCLI($sessionData);

        $this->assertInstanceOf(Session::class, $session);
        $this->assertFalse($session->hasFiles());
    }

    #[Test]
    public function setConversionParamsSetsAllProperties(): void
    {
        $sessionData = $this->createSessionData();
        $session = new TestableSession($sessionData);

        $filters = new FiltersConfigTemplateStruct();
        $filters->id = 1;

        $session->setConversionParams('guid-123', 'it-IT', 'en-US', 'patent', $filters);

        $ref = new ReflectionClass($session);
        $this->assertSame('guid-123', $ref->getProperty('guid')->getValue($session));
        $this->assertSame('it-IT', $ref->getProperty('source_lang')->getValue($session));
        $this->assertSame('en-US', $ref->getProperty('target_lang')->getValue($session));
        $this->assertSame('patent', $ref->getProperty('seg_rule')->getValue($session));
        $this->assertSame($filters, $ref->getProperty('filters_extraction_parameters')->getValue($session));
    }

    #[Test]
    public function setConversionParamsWithNullSegRule(): void
    {
        $sessionData = $this->createSessionData();
        $session = new TestableSession($sessionData);

        $session->setConversionParams('guid-456', 'fr-FR', 'de-DE');

        $ref = new ReflectionClass($session);
        $this->assertSame('guid-456', $ref->getProperty('guid')->getValue($session));
        $this->assertSame('fr-FR', $ref->getProperty('source_lang')->getValue($session));
        $this->assertSame('de-DE', $ref->getProperty('target_lang')->getValue($session));
        $this->assertNull($ref->getProperty('seg_rule')->getValue($session));
        $this->assertNull($ref->getProperty('filters_extraction_parameters')->getValue($session));
    }

    #[Test]
    public function hasFilesReturnsFalseWhenNoFiles(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $this->assertFalse($session->hasFiles());
    }

    #[Test]
    public function hasFilesReturnsTrueWhenFilesExist(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => ['file1' => []],
        ];

        $session = new Session($sessionData);
        $this->assertTrue($session->hasFiles());
    }

    #[Test]
    public function sessionHasFilesReturnsFalseWhenNoFiles(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $this->assertFalse($session->sessionHasFiles());
    }

    #[Test]
    public function sessionHasFilesReturnsTrueWhenFilesExist(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => ['file1' => [Session::FILE_NAME => 'test.docx']],
        ];

        $session = new Session($sessionData);
        $this->assertTrue($session->sessionHasFiles());
    }

    #[Test]
    public function sessionHasFilesReturnsFalseWhenFileListIsEmptyArray(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [],
        ];

        $session = new Session($sessionData);
        $this->assertFalse($session->sessionHasFiles());
    }

    #[Test]
    public function findFileIdByNameReturnsNullWhenNoFiles(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $this->assertNull($session->findFileIdByName('test.docx'));
    }

    #[Test]
    public function findFileIdByNameReturnsFileIdWhenFound(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [
                'file1' => [Session::FILE_NAME => 'doc1.docx'],
                'file2' => [Session::FILE_NAME => 'doc2.pdf'],
            ],
        ];

        $session = new Session($sessionData);
        $this->assertSame('file1', $session->findFileIdByName('doc1.docx'));
        $this->assertSame('file2', $session->findFileIdByName('doc2.pdf'));
    }

    #[Test]
    public function findFileIdByNameReturnsNullWhenNotFound(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [
                'file1' => [Session::FILE_NAME => 'doc1.docx'],
            ],
        ];

        $session = new Session($sessionData);
        $this->assertNull($session->findFileIdByName('nonexistent.docx'));
    }

    #[Test]
    public function clearFileListFromSessionRemovesFileList(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => ['file1' => []],
        ];

        $session = new Session($sessionData);
        $this->assertTrue($session->hasFiles());

        $session->clearFileListFromSession();
        $this->assertFalse($session->hasFiles());
        $this->assertArrayNotHasKey(Session::FILE_LIST, $sessionData[Session::SESSION_KEY]);
    }

    #[Test]
    public function clearSessionRemovesFileList(): void
    {
        $originalDaemon = \Utils\Registry\AppConfig::$IS_DAEMON_INSTANCE;
        \Utils\Registry\AppConfig::$IS_DAEMON_INSTANCE = false;

        try {
            $sessionData = $this->createSessionData();
            $sessionData[Session::SESSION_KEY] = [
                Session::FILE_LIST => ['file1' => []],
            ];

            $session = new Session($sessionData);
            $this->assertTrue($session->hasFiles());

            $session->clearSession();
            $this->assertArrayNotHasKey(Session::FILE_LIST, $sessionData[Session::SESSION_KEY]);
        } finally {
            \Utils\Registry\AppConfig::$IS_DAEMON_INSTANCE = $originalDaemon;
        }
    }

    #[Test]
    public function clearSessionThrowsWhenDaemonInstance(): void
    {
        $originalDaemon = \Utils\Registry\AppConfig::$IS_DAEMON_INSTANCE;
        \Utils\Registry\AppConfig::$IS_DAEMON_INSTANCE = true;

        try {
            $sessionData = $this->createSessionData();
            $session = new Session($sessionData);

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('This method MUST NOT be called from the CLI.');
            $session->clearSession();
        } finally {
            \Utils\Registry\AppConfig::$IS_DAEMON_INSTANCE = $originalDaemon;
        }
    }

    #[Test]
    public function addFilesAddsToFileList(): void
    {
        $sessionData = $this->createSessionData();
        $session = new TestableSession($sessionData);

        $ref = new ReflectionClass($session);
        $serviceStruct = new ConnectedServiceStruct();
        $serviceStruct->id = 1;
        $ref->getProperty('serviceStruct')->setValue($session, $serviceStruct);

        $fileHash = ['cacheHash' => 'abc', 'diskHash' => 'def'];
        $session->addFiles('file1', 'test.docx', $fileHash);

        $this->assertTrue($session->hasFiles());
        $this->assertSame('file1', $session->findFileIdByName('test.docx'));
    }

    #[Test]
    public function addFilesInitializesFileListIfNotSet(): void
    {
        $sessionData = $this->createSessionData();
        $session = new TestableSession($sessionData);

        $ref = new ReflectionClass($session);
        $serviceStruct = new ConnectedServiceStruct();
        $serviceStruct->id = 1;
        $ref->getProperty('serviceStruct')->setValue($session, $serviceStruct);

        $this->assertFalse($session->hasFiles());

        $session->addFiles('file1', 'test.docx', ['cacheHash' => 'abc']);

        $this->assertTrue($session->hasFiles());
    }

    #[Test]
    public function addFilesThrowsWhenServiceStructNotSet(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Service struct not set');
        $session->addFiles('file1', 'test.docx', ['cacheHash' => 'abc']);
    }

    #[Test]
    public function getTokenReturnsNullWhenNoUser(): void
    {
        $sessionData = [
            'uid' => 42,
            'user' => null,
        ];
        $session = new Session($sessionData);

        $this->assertNull($session->getToken());
    }

    #[Test]
    public function getTokenReturnsTokenArray(): void
    {
        $user = $this->createUserStruct();
        $sessionData = [
            'uid' => 42,
            'user' => $user,
        ];

        $session = new TestableSession($sessionData);
        $session->forcedToken = ['access_token' => 'token123'];

        $token = $session->getToken();
        $this->assertIsArray($token);
        $this->assertSame('token123', $token['access_token']);
    }

    #[Test]
    public function getTokenIsMemoized(): void
    {
        $user = $this->createUserStruct();
        $sessionData = [
            'uid' => 42,
            'user' => $user,
        ];

        $session = new TestableSession($sessionData);
        $session->forcedToken = ['access_token' => 'token123'];

        $token1 = $session->getToken();
        $token2 = $session->getToken();

        $this->assertSame($token1, $token2);
    }

    #[Test]
    public function getTokenByUserReturnsDecodedToken(): void
    {
        $user = $this->createUserStruct();
        $sessionData = [
            'uid' => 42,
            'user' => $user,
        ];

        $session = new TestableSession($sessionData);
        $session->forcedToken = ['access_token' => 'user-token-456'];

        $token = $session->getTokenByUser($user);
        $this->assertIsArray($token);
        $this->assertSame('user-token-456', $token['access_token']);
    }

    #[Test]
    public function getTokenByUserReturnsNullWhenNoServiceFound(): void
    {
        $user = $this->createUserStruct();
        $dao = $this->getMockBuilder(ConnectedServiceDao::class)
            ->disableOriginalConstructor()
            ->getMock();
        $dao->expects($this->once())
            ->method('findDefaultServiceByUserAndName')
            ->willReturn(null);

        $sessionData = [
            'uid' => 42,
            'user' => $user,
        ];
        $session = new Session($sessionData, $dao);

        $token = $session->getTokenByUser($user);
        $this->assertNull($token);
    }

    #[Test]
    public function getServiceReturnsDriveServiceWhenTokenAvailable(): void
    {
        $user = $this->createUserStruct();
        $sessionData = [
            'uid' => 42,
            'user' => $user,
        ];

        $session = new TestableSession($sessionData);
        $session->forcedToken = ['access_token' => 'valid-token'];

        $client = $this->getMockBuilder(Google_Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects($this->once())
            ->method('setAccessToken')
            ->with($this->stringContains('valid-token'));

        $service = $session->getService($client);
        $this->assertInstanceOf(Google_Service_Drive::class, $service);
    }

    #[Test]
    public function getServiceReturnsNullWhenNoToken(): void
    {
        $sessionData = [
            'uid' => 42,
            'user' => null,
        ];
        $session = new Session($sessionData);

        $client = $this->createStub(Google_Client::class);

        $service = $session->getService($client);
        $this->assertNull($service);
    }

    #[Test]
    public function getServiceIsMemoized(): void
    {
        $user = $this->createUserStruct();
        $sessionData = [
            'uid' => 42,
            'user' => $user,
        ];

        $session = new TestableSession($sessionData);
        $session->forcedToken = ['access_token' => 'valid-token'];

        $client = $this->getMockBuilder(Google_Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects($this->once())
            ->method('setAccessToken');

        $service1 = $session->getService($client);
        $service2 = $session->getService($client);

        $this->assertSame($service1, $service2);
    }

    #[Test]
    public function buildRemoteFileReturnsRemoteFileService(): void
    {
        $user = $this->createUserStruct();
        $sessionData = [
            'uid' => 42,
            'user' => $user,
        ];

        $session = new TestableSession($sessionData);
        $session->forcedToken = ['access_token' => 'valid-token'];

        $client = $this->getMockBuilder(Google_Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects($this->once())
            ->method('setAccessToken');

        $remoteFile = $session->buildRemoteFile($client);
        $this->assertInstanceOf(RemoteFileService::class, $remoteFile);
    }

    #[Test]
    public function buildRemoteFileThrowsWhenNoToken(): void
    {
        $sessionData = [
            'uid' => 42,
            'user' => null,
        ];
        $session = new Session($sessionData);

        $client = $this->createStub(Google_Client::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot build RemoteFile without a token');
        $session->buildRemoteFile($client);
    }

    #[Test]
    public function getFileStructureForJsonOutputReturnsEmptyWhenNoFiles(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $result = $session->getFileStructureForJsonOutput();
        $this->assertSame([], $result);
    }

    #[Test]
    public function getFileStructureForJsonOutputRemovesNonExistentLocalFiles(): void
    {
        $originalStorageMethod = \Utils\Registry\AppConfig::$FILE_STORAGE_METHOD;
        \Utils\Registry\AppConfig::$FILE_STORAGE_METHOD = 'local';

        try {
            $sessionData = $this->createSessionData();
            $sessionData[Session::SESSION_KEY] = [
                Session::FILE_LIST => [
                    'file1' => [
                        Session::FILE_NAME => 'nonexistent.docx',
                        Session::FILE_HASH => ['cacheHash' => 'abc123abc123abc123abc123'],
                    ],
                ],
            ];

            $session = new Session($sessionData);
            $result = $session->getFileStructureForJsonOutput();
            $this->assertSame([], $result);
            $this->assertFalse($session->hasFiles());
        } finally {
            \Utils\Registry\AppConfig::$FILE_STORAGE_METHOD = $originalStorageMethod;
        }
    }

    #[Test]
    public function getFileStructureForJsonOutputReturnsExistingLocalFile(): void
    {
        $originalStorageMethod = \Utils\Registry\AppConfig::$FILE_STORAGE_METHOD;
        \Utils\Registry\AppConfig::$FILE_STORAGE_METHOD = 'local';

        $originalCacheRepo = \Utils\Registry\AppConfig::$CACHE_REPOSITORY;
        $tempCacheDir = sys_get_temp_dir() . '/matecat-test-gdrive-cache-' . uniqid();
        \Utils\Registry\AppConfig::$CACHE_REPOSITORY = $tempCacheDir;

        try {
            $sessionData = $this->createSessionData();
            $cacheHash = 'abc123abc123abc123abc123';
            $sessionData[Session::SESSION_KEY] = [
                Session::FILE_LIST => [
                    'file1' => [
                        Session::FILE_NAME => 'mydoc.docx',
                        Session::FILE_HASH => ['cacheHash' => $cacheHash],
                    ],
                ],
            ];

            // Create the directory structure that getGDriveFilePath expects:
            // {cache_repo}/ab/c1/23abc123abc123abc123__en-US/package/orig/mydoc.docx
            $fileDir = $tempCacheDir . '/ab/c1/23abc123abc123abc123__en-US/package/orig';
            mkdir($fileDir, 0777, true);
            $filePath = $fileDir . '/mydoc.docx';
            file_put_contents($filePath, 'test document content for coverage');

            $session = new Session($sessionData);
            $result = $session->getFileStructureForJsonOutput();

            $this->assertCount(1, $result['files']);
            $this->assertSame('file1', $result['files'][0]['fileId']);
            $this->assertSame('mydoc.docx', $result['files'][0]['fileName']);
            $this->assertSame(strlen('test document content for coverage'), $result['files'][0]['fileSize']);
            $this->assertSame('docx', $result['files'][0]['fileExtension']);
        } finally {
            \Utils\Registry\AppConfig::$FILE_STORAGE_METHOD = $originalStorageMethod;
            \Utils\Registry\AppConfig::$CACHE_REPOSITORY = $originalCacheRepo;
            self::deleteRecursiveDir($tempCacheDir);
        }
    }

    #[Test]
    public function reConvertConvertsAllFilesSuccessfully(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [
                'file1' => [Session::FILE_NAME => 'doc1.docx', Session::FILE_HASH => ['old']],
                'file2' => [Session::FILE_NAME => 'doc2.docx', Session::FILE_HASH => ['old']],
            ],
        ];

        $session = new TestableSession($sessionData);
        $session->lastConversionHash = ['cacheHash' => 'newHash', 'diskHash' => 'newDiskHash'];

        $result = $session->reConvert('new-lang', null, null);
        $this->assertTrue($result);

        $this->assertSame('newHash', $sessionData[Session::SESSION_KEY][Session::FILE_LIST]['file1'][Session::FILE_HASH]['cacheHash']);
        $this->assertSame('newHash', $sessionData[Session::SESSION_KEY][Session::FILE_LIST]['file2'][Session::FILE_HASH]['cacheHash']);
    }

    #[Test]
    public function reConvertReturnsFalseOnConversionFailure(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [
                'file1' => [Session::FILE_NAME => 'doc1.docx', Session::FILE_HASH => ['old']],
            ],
        ];

        $session = new TestableSession($sessionData);
        $session->conversionException = new Exception('Conversion failed');

        $result = $session->reConvert('new-lang', null, null);
        $this->assertFalse($result);
    }

    #[Test]
    public function reConvertReturnsFalseWhenHashIsEmpty(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [
                'file1' => [Session::FILE_NAME => 'doc1.docx', Session::FILE_HASH => ['old']],
            ],
        ];

        $session = new TestableSession($sessionData);
        $session->lastConversionHash = [];

        $result = $session->reConvert('new-lang', null, null);
        $this->assertFalse($result);
    }

    #[Test]
    public function removeFileReturnsFalseWhenFileNotInList(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $result = $session->removeFile('nonexistent', 'en-US');
        $this->assertFalse($result);
    }

    #[Test]
    public function removeFileReturnsTrueWhenFileExists(): void
    {
        $originalStorageMethod = \Utils\Registry\AppConfig::$FILE_STORAGE_METHOD;
        \Utils\Registry\AppConfig::$FILE_STORAGE_METHOD = 'local';

        $originalCacheRepo = \Utils\Registry\AppConfig::$CACHE_REPOSITORY;
        $tempCacheDir = sys_get_temp_dir() . '/matecat-test-remove-cache-' . uniqid();
        \Utils\Registry\AppConfig::$CACHE_REPOSITORY = $tempCacheDir;

        $originalUploadRepo = \Utils\Registry\AppConfig::$UPLOAD_REPOSITORY;
        $tempUploadDir = sys_get_temp_dir() . '/matecat-test-remove-upload-' . uniqid();
        \Utils\Registry\AppConfig::$UPLOAD_REPOSITORY = $tempUploadDir;

        try {
            $sessionData = $this->createSessionData();
            $cacheHash = 'abc123abc123abc123abc123';

            $sessionData[Session::SESSION_KEY] = [
                Session::FILE_LIST => [
                    'file1' => [
                        Session::FILE_NAME => 'doc1.docx',
                        Session::FILE_HASH => ['cacheHash' => $cacheHash],
                    ],
                ],
            ];

            // Create the cache directory that deleteDirectory needs to delete
            // Path: {cache_repo}/ab/c1/23abc123abc123abc123__en-US/
            $cacheDir = $tempCacheDir . '/ab/c1/23abc123abc123abc123__en-US';
            mkdir($cacheDir, 0777, true);

            // Create the upload dir and a file matching the fileName
            // Path: {upload_repo}/test-upload-token/doc1.docx
            $uploadTokenDir = $tempUploadDir . '/test-upload-token';
            mkdir($uploadTokenDir, 0777, true);
            $uploadedFile = $uploadTokenDir . '/doc1.docx';
            file_put_contents($uploadedFile, 'test document content');

            $session = new Session($sessionData);
            $this->assertTrue($session->hasFiles());

            // suppress E_WARNING from Session.php:416 ($file['fileHash'] array-to-string concat)
            $result = @$session->removeFile('file1', 'en-US');
            $this->assertTrue($result);
            $this->assertFalse($session->hasFiles());
        } finally {
            \Utils\Registry\AppConfig::$FILE_STORAGE_METHOD = $originalStorageMethod;
            \Utils\Registry\AppConfig::$CACHE_REPOSITORY = $originalCacheRepo;
            \Utils\Registry\AppConfig::$UPLOAD_REPOSITORY = $originalUploadRepo;
            self::deleteRecursiveDir($tempCacheDir);
            self::deleteRecursiveDir($tempUploadDir);
        }
    }

    #[Test]
    public function removeAllFilesClearsEmptyFileList(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [],
        ];

        $session = new Session($sessionData);
        $session->removeAllFiles('en-US');
        $this->assertArrayNotHasKey(Session::FILE_LIST, $sessionData[Session::SESSION_KEY]);
    }

    #[Test]
    public function grantFileAccessByUrlThrowsWhenNoUser(): void
    {
        $sessionData = [
            'uid' => 42,
            'user' => null,
        ];
        $session = new Session($sessionData);

        $client = $this->createStub(Google_Client::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot proceed without a User');
        $session->grantFileAccessByUrl('google-file-id', $client);
    }

    #[Test]
    public function grantFileAccessByUrlReturnsPermission(): void
    {
        $user = $this->createUserStruct();
        $sessionData = [
            'uid' => 42,
            'user' => $user,
        ];

        $session = new TestableSession($sessionData);
        $session->forcedToken = ['access_token' => 'valid-token'];

        $expectedPermission = $this->createStub(Google_Service_Drive_Permission::class);

        $permissionsResource = $this->getMockBuilder(Google_Service_Drive_Resource_Permissions::class)
            ->disableOriginalConstructor()
            ->getMock();
        $permissionsResource->expects($this->once())
            ->method('create')
            ->with('google-file-id', $this->isInstanceOf(Google_Service_Drive_Permission::class))
            ->willReturn($expectedPermission);

        $driveService = $this->createStub(Google_Service_Drive::class);
        $driveService->permissions = $permissionsResource;

        $ref = new ReflectionClass($session);
        $ref->getProperty('service')->setValue($session, $driveService);

        $client = $this->createStub(Google_Client::class);

        $result = $session->grantFileAccessByUrl('google-file-id', $client);
        $this->assertSame($expectedPermission, $result);
    }

    #[Test]
    public function grantFileAccessByUrlThrowsWhenServiceIsNull(): void
    {
        $user = $this->createUserStruct();
        $sessionData = [
            'uid' => 42,
            'user' => $user,
        ];
        $session = new Session($sessionData);

        $client = $this->createStub(Google_Client::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot instantiate service');
        $session->grantFileAccessByUrl('google-file-id', $client);
    }

    #[Test]
    public function createRemoteFileCallsGetService(): void
    {
        $user = $this->createUserStruct();
        $sessionData = [
            'uid' => 42,
            'user' => $user,
        ];

        $session = new TestableSession($sessionData);
        $session->forcedToken = ['access_token' => 'valid-token'];

        $ref = new ReflectionClass($session);
        $serviceStruct = new ConnectedServiceStruct();
        $serviceStruct->id = 1;
        $ref->getProperty('serviceStruct')->setValue($session, $serviceStruct);

        $client = $this->getMockBuilder(Google_Client::class)
            ->disableOriginalConstructor()
            ->getMock();
        $client->expects($this->once())
            ->method('setAccessToken');

        $this->createDatabaseMock();

        try {
            $session->createRemoteFile(1, 'remote-file-123', $client);
            $this->assertTrue(true);
        } finally {
            $this->resetDatabaseMock();
        }
    }

    #[Test]
    public function getInstanceForCLIReturnsSessionInstance(): void
    {
        $sessionData = $this->createSessionData();
        $session = Session::getInstanceForCLI($sessionData);

        $this->assertInstanceOf(Session::class, $session);
    }

    #[Test]
    public function importFileThrowsWhenConversionParamsNotSet(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $client = $this->createStub(Google_Client::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('conversion params not set');
        $session->importFile('google-file-id', $client);
    }

    #[Test]
    public function sanitizeFileNameReplacesSlashes(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $method = new \ReflectionMethod($session, 'sanitizeFileName');
        $result = $method->invoke($session, 'path/to/file.docx');

        $this->assertSame('path_to_file.docx', $result);
    }

    #[Test]
    public function sanitizeFileNameThrowsForEmptyName(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $method = new \ReflectionMethod($session, 'sanitizeFileName');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid file name: ');
        $method->invoke($session, '');
    }

    #[Test]
    public function sanitizeFileNameReturnsValidName(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $method = new \ReflectionMethod($session, 'sanitizeFileName');
        $result = $method->invoke($session, 'valid-file.docx');

        $this->assertSame('valid-file.docx', $result);
    }

    #[Test]
    public function deleteDirectoryRemovesDirectory(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $testDir = sys_get_temp_dir() . '/matecat-test-delete-' . uniqid();
        mkdir($testDir, 0777, true);
        file_put_contents($testDir . '/test.txt', 'content');
        mkdir($testDir . '/subdir', 0777, true);
        file_put_contents($testDir . '/subdir/nested.txt', 'nested');

        $this->assertDirectoryExists($testDir);

        $method = new \ReflectionMethod($session, 'deleteDirectory');
        $method->invoke($session, $testDir);

        $this->assertDirectoryDoesNotExist($testDir);
    }

    #[Test]
    public function getCacheFileDirReturnsCorrectPath(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData['actualSourceLang'] = 'it-IT';

        $session = new Session($sessionData);

        $file = [
            Session::FILE_NAME => 'test.docx',
            Session::FILE_HASH => ['cacheHash' => 'abc123'],
        ];

        $method = new \ReflectionMethod($session, 'getCacheFileDir');
        $result = $method->invoke($session, $file);

        $this->assertStringContainsString('it-IT', $result);
        $this->assertStringContainsString('cache-package', $result);
        $this->assertMatchesRegularExpression('#/ab/|/c1/|/23#', $result);
    }

    #[Test]
    public function getGDriveFilePathBuildsPath(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData['actualSourceLang'] = 'en-US';

        $session = new Session($sessionData);

        $file = [
            Session::FILE_NAME => 'mydoc.docx',
            Session::FILE_HASH => ['cacheHash' => 'xyz789'],
        ];

        $method = new \ReflectionMethod($session, 'getGDriveFilePath');
        $result = $method->invoke($session, $file);

        $this->assertStringEndsWith('package' . DIRECTORY_SEPARATOR . 'orig' . DIRECTORY_SEPARATOR . 'mydoc.docx', $result);
        $this->assertMatchesRegularExpression('#/xy/|/z7/|/89#', $result);
    }

    #[Test]
    public function getGDriveFilePathForS3BuildsPath(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData['actualSourceLang'] = 'en-US';

        $session = new Session($sessionData);

        $file = [
            Session::FILE_NAME => 's3doc.pdf',
            Session::FILE_HASH => ['cacheHash' => 'hash-for-s3'],
        ];

        $method = new \ReflectionMethod($session, 'getGDriveFilePathForS3');
        $result = $method->invoke($session, $file);

        $this->assertStringEndsWith('orig' . DIRECTORY_SEPARATOR . 's3doc.pdf', $result);
        $this->assertMatchesRegularExpression('#/ha/|/sh/|/-for-s3#', $result);
    }

    #[Test]
    public function createFeatureSetReturnsFeatureSetInstance(): void
    {
        $sessionData = $this->createSessionData();
        $session = new Session($sessionData);

        $method = new \ReflectionMethod($session, 'createFeatureSet');
        $result = $method->invoke($session);

        $this->assertInstanceOf(\Model\FeaturesBase\FeatureSet::class, $result);
    }

    #[Test]
    public function createFilesConverterReturnsConverterInstance(): void
    {
        $sessionData = $this->createSessionData();
        $session = new TestableSession($sessionData);

        $session->setConversionParams('guid-789', 'fr-FR', 'de-DE', 'general');

        $featureSet = new \Model\FeaturesBase\FeatureSet();
        $ref = new \ReflectionClass($session);
        $ref->getProperty('featureSet')->setValue($session, $featureSet);

        $method = new \ReflectionMethod($session, 'createFilesConverter');
        $result = $method->invoke($session, ['test.docx'], '/tmp/test-upload', '/tmp/test-err', 'upload-token-789');

        $this->assertInstanceOf(\Model\Conversion\FilesConverter::class, $result);
    }

    #[Test]
    public function importFileDownloadsAndConverts(): void
    {
        $origUploadRepo = \Utils\Registry\AppConfig::$UPLOAD_REPOSITORY;
        $tmpDir = sys_get_temp_dir() . '/matecat_upload_test_' . uniqid();
        mkdir($tmpDir, 0755, true);
        \Utils\Registry\AppConfig::$UPLOAD_REPOSITORY = $tmpDir;

        try {
            $sessionData = $this->createSessionData();
            $session = new TestableSession($sessionData);
            $session->setConversionParams('test-guid', 'en-US', 'it-IT');
            $session->lastConversionHash = ['cacheHash' => 'abc123', 'diskHash' => 'def456'];

            $fileMeta = new \Google_Service_Drive_DriveFile();
            $fileMeta->setName('test-document');
            $fileMeta->setMimeType('application/vnd.google-apps.document');

            $body = $this->createStub(\GuzzleHttp\Psr7\Stream::class);
            $body->method('getSize')->willReturn(100);
            $body->method('read')->willReturn('file-content-here');

            $response = $this->createStub(\GuzzleHttp\Psr7\Response::class);
            $response->method('getStatusCode')->willReturn(200);
            $response->method('getBody')->willReturn($body);

            $filesResource = $this->createStub(\Google_Service_Drive_Resource_Files::class);
            $filesResource->method('get')->willReturn($fileMeta);
            $filesResource->method('export')->willReturn($response);

            $mockService = $this->createStub(Google_Service_Drive::class);
            $mockService->files = $filesResource;

            $session->mockService = $mockService;

            $session->importFile('google-file-id-123', $this->createStub(Google_Client::class));

            $this->assertTrue($session->hasFiles());
            $fileId = $session->findFileIdByName('test-document.docx');
            $this->assertSame('google-file-id-123', $fileId);
        } finally {
            \Utils\Registry\AppConfig::$UPLOAD_REPOSITORY = $origUploadRepo;
            self::deleteRecursiveDir($tmpDir);
        }
    }

    #[Test]
    public function importFileThrowsWhenServiceIsNull(): void
    {
        $sessionData = $this->createSessionData();
        $session = new TestableSession($sessionData);
        $session->setConversionParams('test-guid', 'en-US', 'it-IT');
        $session->mockService = null;
        $session->forcedToken = null;

        $this->expectException(Exception::class);
        $session->importFile('google-file-id', $this->createStub(Google_Client::class));
    }

    #[Test]
    public function importFileThrowsOnDownloadError(): void
    {
        $sessionData = $this->createSessionData();
        $session = new TestableSession($sessionData);
        $session->setConversionParams('test-guid', 'en-US', 'it-IT');

        $fileMeta = new \Google_Service_Drive_DriveFile();
        $fileMeta->setName('test.docx');
        $fileMeta->setMimeType('application/vnd.google-apps.document');

        $response = $this->createStub(\GuzzleHttp\Psr7\Response::class);
        $response->method('getStatusCode')->willReturn(500);

        $filesResource = $this->createStub(\Google_Service_Drive_Resource_Files::class);
        $filesResource->method('get')->willReturn($fileMeta);
        $filesResource->method('export')->willReturn($response);

        $mockService = $this->createStub(Google_Service_Drive::class);
        $mockService->files = $filesResource;
        $session->mockService = $mockService;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error when downloading file');

        $session->importFile('google-file-id', $this->createStub(Google_Client::class));
    }

    #[Test]
    public function reConvertReturnsTrueOnSuccess(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [
                'gfile1' => [
                    Session::FILE_NAME => 'doc.docx',
                    Session::FILE_HASH => ['cacheHash' => 'old', 'diskHash' => 'old'],
                ],
            ],
        ];

        $session = new TestableSession($sessionData);
        $session->lastConversionHash = ['cacheHash' => 'new123', 'diskHash' => 'new456'];

        $result = $session->reConvert('fr-FR');

        $this->assertTrue($result);
    }

    #[Test]
    public function reConvertReturnsFalseOnConversionError(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [
                'gfile1' => [
                    Session::FILE_NAME => 'doc.docx',
                    Session::FILE_HASH => ['cacheHash' => 'old', 'diskHash' => 'old'],
                ],
            ],
        ];

        $session = new TestableSession($sessionData);
        $session->conversionException = new Exception('Conversion failed');

        $result = $session->reConvert('fr-FR');

        $this->assertFalse($result);
    }

    #[Test]
    public function reConvertReturnsFalseOnEmptyHash(): void
    {
        $sessionData = $this->createSessionData();
        $sessionData[Session::SESSION_KEY] = [
            Session::FILE_LIST => [
                'gfile1' => [
                    Session::FILE_NAME => 'doc.docx',
                    Session::FILE_HASH => ['cacheHash' => 'old', 'diskHash' => 'old'],
                ],
            ],
        ];

        $session = new TestableSession($sessionData);
        $session->lastConversionHash = [];

        $result = $session->reConvert('fr-FR');

        $this->assertFalse($result);
    }

    #[Test]
    public function importFileThrowsOnEmptyConversionHash(): void
    {
        $origUploadRepo = \Utils\Registry\AppConfig::$UPLOAD_REPOSITORY;
        $tmpDir = sys_get_temp_dir() . '/matecat_upload_test_' . uniqid();
        mkdir($tmpDir, 0755, true);
        \Utils\Registry\AppConfig::$UPLOAD_REPOSITORY = $tmpDir;

        try {
            $sessionData = $this->createSessionData();
            $session = new TestableSession($sessionData);
            $session->setConversionParams('test-guid', 'en-US', 'it-IT');
            $session->lastConversionHash = [];

            $fileMeta = new \Google_Service_Drive_DriveFile();
            $fileMeta->setName('test-document');
            $fileMeta->setMimeType('application/vnd.google-apps.document');

            $body = $this->createStub(\GuzzleHttp\Psr7\Stream::class);
            $body->method('getSize')->willReturn(100);
            $body->method('read')->willReturn('file-content');

            $response = $this->createStub(\GuzzleHttp\Psr7\Response::class);
            $response->method('getStatusCode')->willReturn(200);
            $response->method('getBody')->willReturn($body);

            $filesResource = $this->createStub(\Google_Service_Drive_Resource_Files::class);
            $filesResource->method('get')->willReturn($fileMeta);
            $filesResource->method('export')->willReturn($response);

            $mockService = $this->createStub(Google_Service_Drive::class);
            $mockService->files = $filesResource;
            $session->mockService = $mockService;

            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Error when converting file');

            $session->importFile('file-id', $this->createStub(Google_Client::class));
        } finally {
            \Utils\Registry\AppConfig::$UPLOAD_REPOSITORY = $origUploadRepo;
            self::deleteRecursiveDir($tmpDir);
        }
    }

    private static function deleteRecursiveDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
