<?php

namespace Matecat\Core\Workers\Analysis\MtQueue;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\AsyncTasks\Workers\Analysis\MtQueue\MtQueueLoad;
use Utils\TaskRunner\Commons\Context;

class MtQueueLoadTest extends AbstractTest
{
    /**
     * Builds the load of P4; a cap of 0 is off.
     */
    private static function load(
        int $maxSegments = 0,
        int $maxProjects = 0,
        ?int $segmentLoad = 0,
        ?int $liveProjects = 0,
        int $workers = 30,
        bool $acceptsNewProjects = true
    ): MtQueueLoad {
        $context = Context::buildFromArray([
            'queue_name'          => 'analysis_queue_P4',
            'max_executors'       => 30,
            'max_queued_segments' => $maxSegments,
            'max_queued_projects' => $maxProjects,
            'accept_new_projects' => $acceptsNewProjects,
        ]);

        return new MtQueueLoad('P4', $context, $segmentLoad, $liveProjects, $workers, []);
    }

    /**
     * Rows: segment load, live projects, expected verdict, all under caps 25000 segments and 80 projects.
     *
     * @return array<string, array{?int, ?int, bool}>
     */
    public static function fullUnderCapsProvider(): array
    {
        return [
            'under both caps'                      => [24999, 79, false],
            'segment cap reached'                  => [25000, 79, true],
            'project cap reached, small load'      => [10, 80, true],
            'project cap reached, empty queue'     => [0, 80, true],
            'unknown load, project cap reached'    => [null, 80, true],
            'unknown projects, segment cap passed' => [30000, null, true],
            'both unknown'                         => [null, null, false],
        ];
    }

    #[Test]
    #[DataProvider('fullUnderCapsProvider')]
    public function isFullAppliesEachCapToAKnownValue(?int $segmentLoad, ?int $liveProjects, bool $expected): void
    {
        $load = self::load(maxSegments: 25000, maxProjects: 80, segmentLoad: $segmentLoad, liveProjects: $liveProjects);

        $this->assertSame($expected, $load->isFull());
    }

    #[Test]
    public function isFullIsFalseWhenTheCapsAreOff(): void
    {
        $this->assertFalse(self::load(segmentLoad: 10_000_000, liveProjects: 10_000)->isFull());
    }

    /**
     * Rows: registered workers, accept_new_projects, expected verdict.
     *
     * @return array<string, array{int, bool, bool}>
     */
    public static function usableProvider(): array
    {
        return [
            'no workers'         => [0, true, false],
            'workers, draining'  => [30, false, false],
            'workers, accepting' => [30, true, true],
        ];
    }

    #[Test]
    #[DataProvider('usableProvider')]
    public function isUsableNeedsWorkersAndNewProjectsAccepted(int $workers, bool $acceptsNewProjects, bool $expected): void
    {
        $this->assertSame($expected, self::load(workers: $workers, acceptsNewProjects: $acceptsNewProjects)->isUsable());
    }

    #[Test]
    public function isAvailableNeedsAUsableQueueThatIsNotFull(): void
    {
        $this->assertTrue(self::load(maxSegments: 25000, segmentLoad: 100)->isAvailable());
        $this->assertFalse(self::load(maxSegments: 25000, segmentLoad: 25000)->isAvailable());
        $this->assertFalse(self::load(maxSegments: 25000, segmentLoad: 100, workers: 0)->isAvailable());
    }

    #[Test]
    public function toLogArrayReportsTheLoadTheCapsAndTheVerdict(): void
    {
        $load = self::load(
            maxSegments: 25000,
            maxProjects: 80,
            segmentLoad: 25000,
            liveProjects: null,
            acceptsNewProjects: false
        );

        $this->assertSame([
            'segment_load'  => 25000,
            'live_projects' => null,
            'workers'       => 30,
            'max_segments'  => 25000,
            'max_projects'  => 80,
            'accepts_new'   => false,
            'full'          => true,
        ], $load->toLogArray());
    }
}
