<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Engines\MyMemory;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class SetMultiMyMemoryTest extends AbstractTest
{
    private EngineStruct $engineStruct;

    public function setUp(): void
    {
        parent::setUp();
        $engineDAO = new EngineDAO(Database::obtain(
            AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE
        ));
        $struct = EngineStruct::getStruct();
        $struct->id = 1;
        $eng = $engineDAO->read($struct);
        $this->engineStruct = $eng[0];
    }

    #[Test]
    public function setMultiSendsOneRequestForSingleConfig(): void
    {
        $config = [
            'segment' => 'Il gatto.',
            'translation' => 'The cat.',
            'tnote' => null,
            'source' => 'it-IT',
            'target' => 'en-US',
            'email' => 'demo@matecat.com',
            'prop' => null,
            'id_user' => ['key123'],
        ];

        $engine = $this->getMockBuilder(MyMemory::class)
            ->setConstructorArgs([$this->engineStruct])
            ->onlyMethods(['_call'])
            ->getMock();

        $engine->expects($this->never())->method('_call');

        $engine->setMulti([$config]);

        $this->assertTrue(true);
    }

    #[Test]
    public function setMultiDoesNothingWithEmptyList(): void
    {
        $engine = new MyMemory($this->engineStruct);
        $engine->setMulti([]);
        $this->assertTrue(true);
    }

    #[Test]
    public function setMultiBuildsCorrectPostParameters(): void
    {
        $config = [
            'segment' => 'Source text.',
            'translation' => 'Target text.',
            'tnote' => null,
            'source' => 'it-IT',
            'target' => 'en-US',
            'email' => 'demo@matecat.com',
            'prop' => '{"match-quality":"75"}',
            'id_user' => ['key_abc', 'key_def'],
            'context_after' => null,
            'context_before' => null,
        ];

        $engine = $this->getMockBuilder(MyMemory::class)
            ->setConstructorArgs([$this->engineStruct])
            ->onlyMethods(['_call'])
            ->getMock();

        $capturedUrl = null;
        $capturedParams = null;
        $engine->expects($this->once())->method('_call')
            ->willReturnCallback(function (string $url, array $curlOpt) use (&$capturedUrl, &$capturedParams) {
                $capturedUrl = $url;
                $capturedParams = $curlOpt[CURLOPT_POSTFIELDS];
                return '{"responseData":"OK","responseStatus":200,"responseDetails":[1]}';
            });

        $engine->set($config);

        $this->assertSame('https://api.mymemory.translated.net/set', $capturedUrl);
        $this->assertSame('Source text.', $capturedParams['seg']);
        $this->assertSame('Target text.', $capturedParams['tra']);
        $this->assertSame('it-IT|en-US', $capturedParams['langpair']);
        $this->assertSame('demo@matecat.com', $capturedParams['de']);
        $this->assertSame('key_abc,key_def', $capturedParams['key']);
        $this->assertSame('{"match-quality":"75"}', $capturedParams['prop']);
    }
}
