<?php

namespace Matecat\Core\Utils\Engines;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SplFileObject;
use Utils\Constants\EngineConstants;
use Utils\Engines\MMT;
use Utils\Engines\MMT\MMTServiceApi;

/**
 * Exposes the protected getContext() and lets the test swap in a stub API client.
 */
class TestableMMTForContext extends MMT
{
    public MMTServiceApi $stubClient;

    protected function _getClient(): MMTServiceApi
    {
        return $this->stubClient;
    }

    /**
     * @param string[] $targets
     *
     * @return array<string,string>|null
     * @throws Exception
     */
    public function exposeGetContext(SplFileObject $file, string $source, array $targets): ?array
    {
        return $this->getContext($file, $source, $targets);
    }
}

#[Group('unit')]
class MMTGetContextTest extends AbstractTest
{
    private function makeEngine(MMTServiceApi $client): TestableMMTForContext
    {
        $struct         = new EngineStruct();
        $struct->type   = EngineConstants::MT;
        $engine         = new TestableMMTForContext($struct, $this->createStub(IDatabase::class));
        $engine->stubClient = $client;

        return $engine;
    }

    private function makeSourceFile(): SplFileObject
    {
        $path = tempnam(sys_get_temp_dir(), 'mmt_ctx_test-');
        $file = new SplFileObject($path, 'w+');
        $file->fwrite("hello\n");
        $file->fwrite("world\n");

        return $file;
    }

    #[Test]
    public function getContextDeletesTheGzTempFileOnSuccess(): void
    {
        $client = $this->createStub(MMTServiceApi::class);
        $client->method('getContextVectorFromFile')
            ->willReturn(['vectors' => ['es-ES' => '1:0.1', 'it-IT' => '2:0.2']]);

        $file = $this->makeSourceFile();
        $path = $file->getRealPath();

        try {
            $result = $this->makeEngine($client)->exposeGetContext($file, 'en-US', ['es-ES', 'it-IT']);

            self::assertSame(['en-US|es-ES' => '1:0.1', 'en-US|it-IT' => '2:0.2'], $result);
            // the compressed temp file getContext() creates must not leak on the success path
            self::assertFileDoesNotExist("$path.gz");
        } finally {
            @unlink($path);
            @unlink("$path.gz");
        }
    }

    #[Test]
    public function getContextDeletesTheGzTempFileWhenClientThrows(): void
    {
        $client = $this->createStub(MMTServiceApi::class);
        $client->method('getContextVectorFromFile')
            ->willThrowException(new Exception('boom'));

        $file = $this->makeSourceFile();
        $path = $file->getRealPath();

        try {
            $threw = false;
            try {
                $this->makeEngine($client)->exposeGetContext($file, 'en-US', ['es-ES']);
            } catch (Exception) {
                $threw = true;
            }

            self::assertTrue($threw, 'expected the client exception to propagate');
            self::assertFileDoesNotExist("$path.gz");
        } finally {
            @unlink($path);
            @unlink("$path.gz");
        }
    }
}
