<?php

namespace Matecat\Core\Workers\Analysis\MtQueue;

use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\AsyncTasks\Workers\Analysis\MtQueue\MtQueueLoad;
use Utils\AsyncTasks\Workers\Analysis\MtQueue\MtQueueSelector;
use Utils\TaskRunner\Commons\Context;

class MtQueueSelectorTest extends AbstractTest
{
    /** The project being routed in the pick cases. */
    private const int PID = 77;

    /**
     * Builds the load of one queue with 30 workers, caps 25000 segments (unless given) and 80 projects.
     *
     * @param list<int> $livePids
     */
    private static function load(
        string $key,
        ?int $segmentLoad,
        ?int $liveProjects = 0,
        int $workers = 30,
        bool $acceptsNewProjects = true,
        array $livePids = [],
        int $maxSegments = 25000
    ): MtQueueLoad {
        $context = Context::buildFromArray([
            'queue_name'          => 'analysis_queue_' . $key,
            'max_executors'       => 30,
            'max_queued_segments' => $maxSegments,
            'max_queued_projects' => 80,
            'accept_new_projects' => $acceptsNewProjects,
        ]);

        return new MtQueueLoad($key, $context, $segmentLoad, $liveProjects, $workers, $livePids);
    }

    /**
     * Rows: MT queue loads, expected holds() result.
     *
     * @return array<string, array{list<MtQueueLoad>, bool}>
     */
    public static function holdsProvider(): array
    {
        return [
            'no mt queue'                 => [[], false],
            'P4 full'                     => [[self::load('P4', 30000)], true],
            'P4 full, P5 open'            => [[self::load('P4', 30000), self::load('P5', 100)], false],
            'P4 full, P5 open, no worker' => [[self::load('P4', 30000), self::load('P5', 100, workers: 0)], true],
            'P4 draining, P5 full'        => [[self::load('P4', 100, acceptsNewProjects: false), self::load('P5', 30000)], true],
        ];
    }

    /**
     * @param list<MtQueueLoad> $loads
     */
    #[Test]
    #[DataProvider('holdsProvider')]
    public function holdsWhenNoMtQueueIsAvailable(array $loads, bool $expected): void
    {
        $this->assertSame($expected, MtQueueSelector::holds($loads));
    }

    /**
     * Rows: MT queue loads, key of the queue expected for the project.
     *
     * @return array<string, array{list<MtQueueLoad>, string}>
     */
    public static function pickProvider(): array
    {
        return [
            'lower load wins'                 => [[self::load('P4', 5000), self::load('P5', 100)], 'P5'],
            'sticky on the queue listing it'  => [[self::load('P4', 5000, livePids: [self::PID]), self::load('P5', 0)], 'P4'],
            'sticky on the second queue'      => [[self::load('P4', 100), self::load('P5', 5000, livePids: [self::PID])], 'P5'],
            'sticky even on a full queue'     => [[self::load('P4', 30000, livePids: [self::PID]), self::load('P5', 100)], 'P4'],
            'sticky even on a draining queue' => [
                [self::load('P4', 100, acceptsNewProjects: false, livePids: [self::PID]), self::load('P5', 0)],
                'P4',
            ],
            'other pid listed, not sticky'    => [[self::load('P4', 5000, livePids: [self::PID + 1]), self::load('P5', 100)], 'P5'],
            'equal loads, first wins'         => [[self::load('P4', 100), self::load('P5', 100)], 'P4'],
            'unknown first, known wins'       => [[self::load('P4', null), self::load('P5', 300)], 'P5'],
            'full queue skipped for open one' => [
                [self::load('P4', 100, liveProjects: 80), self::load('P5', 30000, maxSegments: 50000)],
                'P5',
            ],
            // nothing available happens only in a race after the hold check: the project goes to the first queue
            'both full, first wins'           => [[self::load('P4', 30000), self::load('P5', 28000)], 'P4'],
            'known load beats unknown'        => [[self::load('P4', 5000), self::load('P5', null)], 'P4'],
            'both unknown, first wins'        => [[self::load('P4', null), self::load('P5', null)], 'P4'],
            'queue with no worker is skipped' => [[self::load('P4', 9000), self::load('P5', 10, workers: 0)], 'P4'],
            'no worker anywhere, first wins'  => [[self::load('P4', 9000, workers: 0), self::load('P5', 10, workers: 0)], 'P4'],
        ];
    }

    /**
     * @param list<MtQueueLoad> $loads
     */
    #[Test]
    #[DataProvider('pickProvider')]
    public function pickChoosesTheQueueForTheProject(array $loads, string $expectedKey): void
    {
        $this->assertSame($expectedKey, MtQueueSelector::pick($loads, self::PID)->key);
    }

    #[Test]
    public function pickRefusesAnEmptyList(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MtQueueSelector::pick([], self::PID);
    }
}
