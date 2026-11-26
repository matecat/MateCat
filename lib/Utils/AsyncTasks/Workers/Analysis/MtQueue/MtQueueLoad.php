<?php

namespace Utils\AsyncTasks\Workers\Analysis\MtQueue;

use Utils\TaskRunner\Commons\Context;

/**
 * How busy one MT analysis queue is right now, read once per check.
 *
 * Example: P4 with 25000 segments queued under a cap of 25000 segments is full.
 */
final readonly class MtQueueLoad
{
    /**
     * @param string    $key          Context list key, such as 'P4'.
     * @param Context   $context      The queue's settings: its caps and whether it accepts new projects.
     * @param ?int      $segmentLoad  Segment messages still to analyse; null when the read failed.
     * @param ?int      $liveProjects Projects still in analysis on this queue, the count of `$livePids`; null when the read failed.
     * @param int       $workers      Workers registered on the queue: the size of its `<queue_name>_pid_set`.
     * @param list<int> $livePids     Projects this queue is analysing now, to keep a retried project on its queue.
     */
    public function __construct(
        public string $key,
        public Context $context,
        public ?int $segmentLoad,
        public ?int $liveProjects,
        public int $workers,
        public array $livePids
    ) {
    }

    /**
     * Tells whether a cap that is on has been reached; a failed read never makes the queue full.
     *
     * Example: cap 80 projects with 80 live projects is full even when the segment load is 0.
     */
    public function isFull(): bool
    {
        return self::capReached($this->context->max_queued_segments, $this->segmentLoad)
            || self::capReached($this->context->max_queued_projects, $this->liveProjects);
    }

    /**
     * Tells whether a new project can go here now: the queue has workers, is not being drained and is not full.
     *
     * Example: P5 with 30 workers and 100 segments queued under a 25000 cap is available; with
     * `accept_new_projects` off, or with no worker, it is not.
     */
    public function isAvailable(): bool
    {
        return $this->workers > 0 && $this->context->accept_new_projects && !$this->isFull();
    }

    /**
     * The numbers behind the verdict, for the queue state log.
     *
     * Example: ['segment_load' => 25000, 'live_projects' => null, ..., 'full' => true].
     *
     * @return array{segment_load: ?int, live_projects: ?int, workers: int, max_segments: int, max_projects: int, accepts_new: bool, full: bool}
     */
    public function toLogArray(): array
    {
        return [
            'segment_load'  => $this->segmentLoad,
            'live_projects' => $this->liveProjects,
            'workers'       => $this->workers,
            'max_segments'  => $this->context->max_queued_segments,
            'max_projects'  => $this->context->max_queued_projects,
            'accepts_new'   => $this->context->accept_new_projects,
            'full'          => $this->isFull(),
        ];
    }

    /**
     * A cap of 0 is off; an unknown value (null) never reaches a cap.
     */
    private static function capReached(int $cap, ?int $value): bool
    {
        return $cap > 0 && $value !== null && $value >= $cap;
    }
}
