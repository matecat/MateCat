<?php

namespace Matecat\Core\TestMyMemory;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\EngineConstants;
use Utils\Engines\MyMemory;

/**
 * `mt_threshold` carries the project's MT application threshold to MyMemory, clamped to 0-100,
 * and only when MT is requested.
 */
class MtThresholdMyMemoryTest extends AbstractTest
{
    private EngineStruct $engineStruct;

    public function setUp(): void
    {
        parent::setUp();
        $this->engineStruct = new EngineStruct([
            'id' => 1,
            'name' => 'MyMemory',
            'type' => EngineConstants::TM,
            'base_url' => 'https://api.mymemory.translated.net',
            'translate_relative_url' => 'get',
            'class_load' => 'MyMemory',
            'others' => [],
            'extra_parameters' => [],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function sentParameters(array $config): array
    {
        $captured = [];
        $engine = $this->getMockBuilder(MyMemory::class)
            ->setConstructorArgs([$this->engineStruct, $this->createStub(IDatabase::class)])
            ->onlyMethods(['_call'])
            ->getMock();

        $engine->expects($this->once())->method('_call')
            ->willReturnCallback(function (string $url, array $curlOpt) use (&$captured) {
                $captured = $curlOpt[CURLOPT_POSTFIELDS];

                return '{"responseData":{"translatedText":""},"responseStatus":200,"responseDetails":"","matches":[]}';
            });

        $engine->get(array_merge([
            'segment' => 'Il gatto.',
            'source' => 'it-IT',
            'target' => 'en-US',
            'email' => 'demo@matecat.com',
            'get_mt' => true,
            'num_result' => 3,
        ], $config));

        return $captured;
    }

    #[Test]
    public function getSendsTheMtThreshold(): void
    {
        $this->assertSame(85, $this->sentParameters(['mt_threshold' => 85])['mt_threshold']);
    }

    #[Test]
    public function getClampsTheMtThresholdTo100(): void
    {
        $this->assertSame(100, $this->sentParameters(['mt_threshold' => 101])['mt_threshold']);
    }

    #[Test]
    public function mtThresholdIsLeftOutWhenNotSet(): void
    {
        $this->assertArrayNotHasKey('mt_threshold', $this->sentParameters([]));
    }

    #[Test]
    public function mtThresholdIsLeftOutWhenMtIsNotRequested(): void
    {
        $this->assertArrayNotHasKey('mt_threshold', $this->sentParameters(['get_mt' => false, 'mt_threshold' => 85]));
    }
}
