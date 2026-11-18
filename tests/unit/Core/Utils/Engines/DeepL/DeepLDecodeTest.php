<?php

namespace Matecat\Core\Utils\Engines\DeepL;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TypeError;
use Utils\Constants\EngineConstants;
use Utils\Engines\DeepL;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

/**
 * Exposes the protected _decode() so the match score can be checked without calling DeepL.
 */
class TestableDeepLForDecode extends DeepL
{
    /**
     * @throws Exception
     * @throws TypeError
     */
    public function exposeDecode(string $rawValue, array $parameters): GetMemoryResponse
    {
        return $this->_decode($rawValue, $parameters);
    }
}

/**
 * The MT row's score is the editor's "Application threshold": the worker sets it as a penalty
 * (100 - threshold) and the engine turns it back into the match percentage that ranks the MT
 * row against the TM matches. DeepL used to ignore it and always answer 85%.
 */
#[Group('unit')]
class DeepLDecodeTest extends AbstractTest
{
    private function makeEngine(?int $recordPenalty = null): TestableDeepLForDecode
    {
        $struct = new EngineStruct();
        $struct->type = EngineConstants::MT;
        $struct->name = 'DeepL';
        $struct->penalty = $recordPenalty;

        return new TestableDeepLForDecode($struct, $this->createStub(IDatabase::class));
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    private function decodedMatch(TestableDeepLForDecode $engine): mixed
    {
        $response = $engine->exposeDecode(
            (string)json_encode(['translations' => [['text' => 'Ciao mondo']]]),
            ['source_lang' => 'en', 'target_lang' => 'it', 'text' => ['Hello world']]
        );

        $this->assertCount(1, $response->matches);

        return $response->matches[0]->match;
    }

    #[Test]
    public function matchScoreFollowsTheApplicationThreshold(): void
    {
        $engine = $this->makeEngine();
        $engine->setMTPenalty(100 - 99);

        $this->assertSame('99%', $this->decodedMatch($engine));
    }

    #[Test]
    public function matchScoreCanExceedOneHundredWhenTheThresholdDoes(): void
    {
        $engine = $this->makeEngine();
        $engine->setMTPenalty(100 - 102);

        $this->assertSame('102%', $this->decodedMatch($engine));
    }

    #[Test]
    public function matchScoreFallsBackToTheEngineRecordPenalty(): void
    {
        $this->assertSame('80%', $this->decodedMatch($this->makeEngine(20)));
    }

    #[Test]
    public function matchScoreFallsBackToTheDefaultPenaltyLikeEveryOtherEngine(): void
    {
        $this->assertSame('86%', $this->decodedMatch($this->makeEngine()));
    }
}
