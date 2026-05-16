<?php

declare(strict_types=1);

namespace unit\Engines;

use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;
use Utils\Engines\NONE;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

class NONEEngineIntegrationTest extends AbstractTest
{
    private NONE $engine;

    protected function setUp(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        $instance = EnginesFactory::createTempInstance($struct);
        self::assertInstanceOf(NONE::class, $instance);

        /** @var NONE $instance */
        $this->engine = $instance;
    }

    #[Test]
    public function getReturnsGetMemoryResponseUsableByTMAnalysisWorker(): void
    {
        $mt_result = $this->engine->get([]);

        self::assertInstanceOf(GetMemoryResponse::class, $mt_result);
        self::assertSame(200, $mt_result->responseStatus);

        $matches = $mt_result->get_matches_as_array(1);
        self::assertIsArray($matches);
        self::assertEmpty($matches['matches'] ?? []);
    }

    #[Test]
    public function getResponseStatusIsBelow400SoWorkerDoesNotReturnEarly(): void
    {
        $mt_result = $this->engine->get([]);

        self::assertLessThan(400, $mt_result->responseStatus);
    }

    #[Test]
    public function isNotAdaptiveMTNorTMSSoSetContributionWorkersSkip(): void
    {
        self::assertFalse($this->engine->isAdaptiveMT());
        self::assertFalse($this->engine->isTMS());
    }

    #[Test]
    public function setReturnsTrueWhichPassesMTWorkerNegationGuard(): void
    {
        $res = $this->engine->set([]);

        self::assertTrue($res);
        self::assertFalse(!$res);
    }

    #[Test]
    public function updateReturnsTrueWhichPassesMTWorkerNegationGuard(): void
    {
        $res = $this->engine->update([]);

        self::assertTrue($res);
        self::assertFalse(!$res);
    }

    #[Test]
    public function deleteReturnsTrueConformingToInterfaceContract(): void
    {
        $res = $this->engine->delete([]);

        self::assertTrue($res);
    }

    #[Test]
    public function getConfigStructReturnsArray(): void
    {
        $config = $this->engine->getConfigStruct();

        self::assertIsArray($config);
    }

    #[Test]
    public function engineIsInstanceOfAbstractEngine(): void
    {
        self::assertInstanceOf(AbstractEngine::class, $this->engine);
    }
}
