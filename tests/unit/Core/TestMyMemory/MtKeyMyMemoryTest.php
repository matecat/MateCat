<?php

namespace Matecat\Core\TestMyMemory;

use Matecat\TestHelpers\AbstractTest;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Engines\MyMemory;
use Utils\Registry\AppConfig;

/**
 * `key` carries every key the user can use on the job; `mt_key` carries only the owner's keys.
 */
#[Group('PersistenceNeeded')]
class MtKeyMyMemoryTest extends AbstractTest
{
    private EngineStruct $engineStruct;

    public function setUp(): void
    {
        parent::setUp();
        $engineDAO = new EngineDAO(obtainTestDatabase(
            AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE
        ));
        $struct = EngineStruct::getStruct();
        $struct->id = 1;
        $this->engineStruct = $engineDAO->read($struct)[0];
    }

    /**
     * @param array<string, mixed> $captured
     */
    private function engineCapturing(array &$captured, string $response): MyMemory
    {
        $engine = $this->getMockBuilder(MyMemory::class)
            ->setConstructorArgs([$this->engineStruct, $this->createStub(\Model\DataAccess\IDatabase::class)])
            ->onlyMethods(['_call'])
            ->getMock();

        $engine->expects($this->once())->method('_call')
            ->willReturnCallback(function (string $url, array $curlOpt) use (&$captured, $response) {
                $captured = $curlOpt[CURLOPT_POSTFIELDS];

                return $response;
            });

        return $engine;
    }

    /**
     * @return array<string, mixed>
     */
    private function writeConfig(array $keys): array
    {
        return array_merge([
            'segment' => 'Il gatto.',
            'translation' => 'The cat.',
            'newsegment' => 'Il gatto.',
            'newtranslation' => 'The cat!',
            'tnote' => null,
            'source' => 'it-IT',
            'target' => 'en-US',
            'email' => 'demo@matecat.com',
            'prop' => null,
            'spiceMatch' => false,
        ], $keys);
    }

    /**
     * @return array<string, mixed>
     */
    private function readConfig(array $keys): array
    {
        return array_merge([
            'segment' => 'Il gatto.',
            'source' => 'it-IT',
            'target' => 'en-US',
            'email' => 'demo@matecat.com',
            'get_mt' => false,
            'num_result' => 3,
        ], $keys);
    }

    #[Test]
    public function getSendsMtKeyAsACommaSeparatedString(): void
    {
        $captured = [];
        $engine = $this->engineCapturing($captured, '{"responseData":{"translatedText":""},"responseStatus":200,"responseDetails":"","matches":[]}');

        $engine->get($this->readConfig(['id_user' => ['owner1', 'owner2', 'mine'], 'mt_key' => ['owner1', 'owner2']]));

        $this->assertSame('owner1,owner2,mine', $captured['key']);
        $this->assertSame('owner1,owner2', $captured['mt_key']);
    }

    #[Test]
    public function setSendsMtKey(): void
    {
        $captured = [];
        $engine = $this->engineCapturing($captured, '{"responseData":"OK","responseStatus":200,"responseDetails":[1]}');

        $engine->set($this->writeConfig(['id_user' => ['owner1', 'mine'], 'mt_key' => ['owner1']]));

        $this->assertSame('owner1,mine', $captured['key']);
        $this->assertSame('owner1', $captured['mt_key']);
    }

    #[Test]
    public function updateSendsMtKey(): void
    {
        $captured = [];
        $engine = $this->engineCapturing($captured, '{"responseData":"OK","responseStatus":200,"responseDetails":[1]}');

        $engine->update($this->writeConfig(['id_user' => ['owner1', 'mine'], 'mt_key' => ['owner1']]));

        $this->assertSame('owner1,mine', $captured['key']);
        $this->assertSame('owner1', $captured['mt_key']);
    }

    #[Test]
    public function mtKeyIsLeftOutWhenTheJobHasNoOwnerKeys(): void
    {
        $captured = [];
        $engine = $this->engineCapturing($captured, '{"responseData":"OK","responseStatus":200,"responseDetails":[1]}');

        $engine->update($this->writeConfig(['id_user' => ['mine'], 'mt_key' => []]));

        $this->assertSame('mine', $captured['key']);
        $this->assertArrayNotHasKey('mt_key', $captured);
    }
}
