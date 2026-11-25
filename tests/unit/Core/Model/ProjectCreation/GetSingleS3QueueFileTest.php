<?php

namespace Matecat\Core\Model\ProjectCreation;

use Aws\CommandInterface;
use Aws\Result;
use Exception;
use GuzzleHttp\Promise\Create;
use Matecat\SimpleS3\Client as S3Client;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\FilesStorage\S3FilesStorage;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Throwable;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::getSingleS3QueueFile()}.
 *
 * The worker downloads a native xliff from the queue folder by the name the user uploaded. It must ask
 * for the key the upload wrote ({@see S3FilesStorage::getQueueObjectKey()}): S3 keys are
 * case-sensitive, so `Test.XLIFF` asked as written by a lower-casing upload is a missing key.
 */
class GetSingleS3QueueFileTest extends AbstractTest
{
    private TestableProjectManager $pm;
    private string $uploadDir;
    private string $storageMethod;
    private string $bucket;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->storageMethod = AppConfig::$FILE_STORAGE_METHOD;
        $this->bucket = AppConfig::$AWS_STORAGE_BASE_BUCKET;
        AppConfig::$FILE_STORAGE_METHOD = 's3';
        // No request leaves the process: the AWS transport is mocked.
        AppConfig::$AWS_STORAGE_BASE_BUCKET = 'unit-test-bucket';

        $this->uploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '{FD750C37-2D33-4913-BDD1-' . strtoupper(bin2hex(random_bytes(6))) . '}';

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setUploadDir($this->uploadDir);
    }

    protected function tearDown(): void
    {
        AppConfig::$FILE_STORAGE_METHOD = $this->storageMethod;
        AppConfig::$AWS_STORAGE_BASE_BUCKET = $this->bucket;
        if (is_dir($this->uploadDir)) {
            exec('rm -rf ' . escapeshellarg($this->uploadDir));
        }
        parent::tearDown();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function asksForTheKeyTheUploadWroteKeepingTheCaseOfTheExtension(): void
    {
        $requests = $this->download('Test stati xliff 1.2.XLIFF');

        $uploadSession = basename($this->uploadDir);
        $this->assertSame([['GetObject', 'unit-test-bucket', S3FilesStorage::getQueueObjectKey($uploadSession, 'Test stati xliff 1.2.XLIFF')]], $requests);
        $this->assertStringEndsWith('/Test stati xliff 1.2.XLIFF', $requests[0][2]);
        $this->assertSame('<xliff/>', file_get_contents($this->uploadDir . '/Test stati xliff 1.2.XLIFF'));
    }

    /**
     * A name too long for a key is asked by the hashed name the upload wrote, not by its own.
     *
     * @throws Throwable
     */
    #[Test]
    public function asksForTheHashedKeyOfALongName(): void
    {
        $fileName = str_repeat('a', 240) . '.xlf';

        $requests = $this->download($fileName);

        $this->assertSame(S3FilesStorage::getQueueObjectKey(basename($this->uploadDir), $fileName), $requests[0][2]);
        $this->assertStringEndsWith('/' . sha1(str_repeat('a', 240)) . '.xlf', $requests[0][2]);
    }

    /**
     * Runs getSingleS3QueueFile() against a mocked AWS transport that answers every GetObject with
     * `<xliff/>`, and returns the requests it received.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     * @throws Throwable
     */
    private function download(string $fileName): array
    {
        $requests = [];
        $configured = S3FilesStorage::getStaticS3Client();
        $client = new ReflectionProperty(S3FilesStorage::class, 'CLIENT');
        $client->setValue(null, new S3Client([
            'version' => 'latest',
            'region' => 'eu-central-1',
            'credentials' => ['key' => 'test', 'secret' => 'test'],
            'handler' => static function (CommandInterface $command) use (&$requests) {
                $requests[] = [$command->getName(), (string)$command['Bucket'], (string)$command['Key']];
                file_put_contents((string)($command['SaveAs'] ?? $command['@http']['sink']), '<xliff/>');

                return Create::promiseFor(new Result(['@metadata' => ['statusCode' => 200]]));
            },
        ]));

        try {
            $this->pm->getSingleS3QueueFile($fileName);
        } finally {
            $client->setValue(null, $configured);
        }

        return $requests;
    }
}
