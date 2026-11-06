<?php

declare(strict_types=1);

namespace Matecat\Core\Engines\Lara;

use Lara\LaraException;
use Lara\QualityEstimationResult;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Utils\Engines\EngineInterface;
use Utils\Engines\Lara;
use Utils\Engines\Lara\LaraClient;
use Utils\Engines\Lara\LaraQualityEstimator;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\Matches;

class LaraQualityEstimatorTest extends AbstractTest
{
    private const NO_LARA_ENGINE = 'MT quality estimation skipped: the engine owner has no active Lara engine';

    private EngineStruct $record;
    private GetMemoryResponse $response;
    /** @var array<string, mixed> */
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->record = EngineStruct::getStruct();
        $this->record->id = 15;
        $this->record->uid = 42;
        $this->record->active = true;

        $this->response = new GetMemoryResponse(null);
        $this->response->matches = [$this->match()];

        $this->config = ['include_score' => true, 'source' => 'en-US', 'target' => 'it-IT', 'segment' => 'source segment'];
    }

    #[Test]
    public function addScoreSetsTheLaraScoreOnTheFirstMatch(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $estimator = $this->estimator($this->laraEngine($this->scoringClient()), $logger);
        $estimator->addScore($this->mtEngine(), $this->response, $this->config);

        self::assertSame(0.83, $this->response->matches[0]->score);
        self::assertSame([42], $estimator->lookedUpOwners);
    }

    #[Test]
    public function addScoreScoresAnActiveLaraEngineWithItsOwnClient(): void
    {
        $estimator = $this->estimator($this->laraEngine(null), $this->createStub(LoggerInterface::class));
        $estimator->addScore($this->laraMtEngine($this->scoringClient()), $this->response, $this->config);

        self::assertSame(0.83, $this->response->matches[0]->score);
        self::assertSame([], $estimator->lookedUpOwners);
    }

    #[Test]
    public function addScoreScoresADisabledLaraEngineWithTheOwnerActiveLaraEngine(): void
    {
        $this->record->active = false;

        $estimator = $this->estimator($this->laraEngine($this->scoringClient()), $this->createStub(LoggerInterface::class));
        $estimator->addScore($this->laraMtEngine(null), $this->response, $this->config);

        self::assertSame(0.83, $this->response->matches[0]->score);
        self::assertSame([42], $estimator->lookedUpOwners);
    }

    #[Test]
    public function addScoreSkipsWhenIncludeScoreIsOff(): void
    {
        $this->config['include_score'] = false;

        $this->assertSkipped();

        self::assertNull($this->response->matches[0]->score);
    }

    #[Test]
    public function addScoreSkipsWhenIncludeScoreIsMissing(): void
    {
        unset($this->config['include_score']);

        $this->assertSkipped();

        self::assertNull($this->response->matches[0]->score);
    }

    #[Test]
    public function addScoreSkipsEnginesWithBuiltInQualityEstimation(): void
    {
        $this->response->matches = [$this->match(['score' => 0.95])];

        $this->assertSkipped($this->mtEngine(builtInQualityEstimation: true));

        self::assertSame(0.95, $this->response->matches[0]->score);
    }

    #[Test]
    public function addScoreSkipsAnErrorResponse(): void
    {
        $this->response->responseStatus = 400;

        $this->assertSkipped();

        self::assertNull($this->response->matches[0]->score);
    }

    #[Test]
    public function addScoreSkipsAResponseWithoutMatches(): void
    {
        $this->response->matches = [];

        $this->assertSkipped();

        self::assertSame([], $this->response->matches);
    }

    #[Test]
    public function addScoreSkipsAnEmptyTranslation(): void
    {
        $this->response->matches = [$this->match(['raw_translation' => ''])];

        $this->assertSkipped();

        self::assertNull($this->response->matches[0]->score);
    }

    #[Test]
    public function addScoreLeavesScoreNullAndWarnsWithoutActiveLaraEngine(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(self::NO_LARA_ENGINE, ['uid' => 42, 'engine_id' => 15]);

        $estimator = $this->estimator(null, $logger);
        $estimator->addScore($this->mtEngine(), $this->response, $this->config);

        self::assertNull($this->response->matches[0]->score);
        self::assertSame([42], $estimator->lookedUpOwners);
    }

    #[Test]
    public function addScoreLeavesScoreNullAndWarnsOnLaraError(): void
    {
        $client = $this->createMock(LaraClient::class);
        $client->expects($this->once())->method('qualityEstimation')
            ->with('en-US', 'it-IT', 'source segment', 'mt translation')
            ->willThrowException(new LaraException('failed', 429));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            'MT quality estimation failed',
            ['uid' => 42, 'engine_id' => 15, 'error' => 'failed', 'code' => 429]
        );

        $this->estimator($this->laraEngine($client), $logger)->addScore($this->mtEngine(), $this->response, $this->config);

        self::assertNull($this->response->matches[0]->score);
    }

    #[Test]
    public function addScoreLeavesScoreNullWhenResponseHasNoSingleScore(): void
    {
        $client = $this->createMock(LaraClient::class);
        $client->expects($this->once())->method('qualityEstimation')
            ->with('en-US', 'it-IT', 'source segment', 'mt translation')
            ->willReturn([]);

        $this->estimator($this->laraEngine($client), $this->createStub(LoggerInterface::class))
            ->addScore($this->mtEngine(), $this->response, $this->config);

        self::assertNull($this->response->matches[0]->score);
    }

    #[Test]
    public function addScoreLeavesScoreNullAndWarnsWhenTheEngineHasNoOwner(): void
    {
        $this->record->uid = null;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(self::NO_LARA_ENGINE, ['uid' => null, 'engine_id' => 15]);

        $estimator = $this->estimator(null, $logger);
        $estimator->addScore($this->mtEngine(), $this->response, $this->config);

        self::assertNull($this->response->matches[0]->score);
        self::assertSame([null], $estimator->lookedUpOwners);
    }

    /**
     * Runs addScore() and checks that it stopped before looking up the owner's Lara engine or calling Lara.
     */
    private function assertSkipped(?EngineInterface $mtEngine = null): void
    {
        $estimator = $this->estimator(null, $this->createStub(LoggerInterface::class));
        $estimator->addScore($mtEngine ?? $this->mtEngine(), $this->response, $this->config);

        self::assertSame([], $estimator->lookedUpOwners);
    }

    /**
     * A client that must score the base case once, with 0.83.
     */
    private function scoringClient(): LaraClient
    {
        $client = $this->createMock(LaraClient::class);
        $client->expects($this->once())->method('qualityEstimation')
            ->with('en-US', 'it-IT', 'source segment', 'mt translation')
            ->willReturn(new QualityEstimationResult(0.83));

        return $client;
    }

    /**
     * A non-Lara MT engine on the base record.
     */
    private function mtEngine(bool $builtInQualityEstimation = false): EngineInterface
    {
        $mtEngine = $this->createStub(EngineInterface::class);
        $mtEngine->method('hasBuiltInQualityEstimation')->willReturn($builtInQualityEstimation);
        $mtEngine->method('getEngineRecord')->willReturn($this->record);

        return $mtEngine;
    }

    /**
     * A Lara MT engine on the base record; see laraEngine() for the client.
     */
    private function laraMtEngine(?LaraClient $client): Lara
    {
        $lara = $this->laraEngine($client);
        $lara->method('getEngineRecord')->willReturn($this->record);

        return $lara;
    }

    /**
     * A Lara engine that hands out the client once, or, without a client, is never asked for one.
     */
    private function laraEngine(?LaraClient $client): Lara
    {
        $lara = $this->createMock(Lara::class);

        if ($client === null) {
            $lara->expects($this->never())->method('getLaraClient');
        } else {
            $lara->expects($this->once())->method('getLaraClient')->willReturn($client);
        }

        return $lara;
    }

    /**
     * @param array<string, mixed> $override
     */
    private function match(array $override = []): Matches
    {
        return new Matches($override + [
            'source' => 'en-US',
            'target' => 'it-IT',
            'raw_segment' => 'source segment',
            'raw_translation' => 'mt translation',
        ]);
    }

    private function estimator(?Lara $ownerLaraEngine, LoggerInterface $logger): TestableLaraQualityEstimator
    {
        $estimator = new TestableLaraQualityEstimator($this->createStub(IDatabase::class), $logger);
        $estimator->ownerLaraEngine = $ownerLaraEngine;

        return $estimator;
    }
}

class TestableLaraQualityEstimator extends LaraQualityEstimator
{
    public ?Lara $ownerLaraEngine = null;
    /** @var list<int|null> */
    public array $lookedUpOwners = [];

    protected function findOwnerLaraEngine(?int $uid): ?Lara
    {
        $this->lookedUpOwners[] = $uid;

        return $this->ownerLaraEngine;
    }
}
