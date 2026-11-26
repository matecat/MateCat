<?php

namespace Utils\AsyncTasks\Workers\Analysis\MtQueue;

use InvalidArgumentException;

/**
 * Decides whether MT projects must wait, and which MT queue takes a project.
 *
 * Example: P4 with 5000 segments queued and P5 with 100, both available: a new Lara project goes to P5.
 */
final class MtQueueSelector
{
    /**
     * Tells whether MT projects must wait in NEW: there is an MT queue, and none is available.
     *
     * Example: P4 full and P5 open gives false; P4 full and P5 open but with no worker gives true.
     *
     * @param list<MtQueueLoad> $loads
     */
    public static function holds(array $loads): bool
    {
        if ($loads === []) {
            return false;
        }

        foreach ($loads as $load) {
            if ($load->isAvailable()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Chooses the queue for a project: the one already analysing it, else the lightest available queue,
     * else the lightest usable queue, else the first queue.
     *
     * Example: for pid 77, P4 at 5000 and P5 at 100 gives P5, unless P4 already lists pid 77 as live.
     *
     * @param list<MtQueueLoad> $loads MT queues in context list order, P4 first.
     *
     * @throws InvalidArgumentException When there is no MT queue at all.
     */
    public static function pick(array $loads, int $pid): MtQueueLoad
    {
        if ($loads === []) {
            throw new InvalidArgumentException('No MT queue to pick from.');
        }

        // A retried project stays on its queue even when that queue is full or drained,
        // so its segments are not split across two queues.
        foreach ($loads as $load) {
            if (in_array($pid, $load->livePids, true)) {
                return $load;
            }
        }

        $usableQueues    = array_filter($loads, static fn(MtQueueLoad $load): bool => $load->isUsable());
        $availableQueues = array_filter($usableQueues, static fn(MtQueueLoad $load): bool => $load->isAvailable());

        // Prefer an available queue, then any usable one, then the first queue (only when nothing is usable).
        return self::lightest($availableQueues) ?? self::lightest($usableQueues) ?? $loads[0];
    }

    /**
     * Returns the queue with the lowest known segment load; an unknown load comes after every known one,
     * and on a tie the earlier queue wins.
     *
     * Example: [P4 null, P5 300] gives P5; [P4 null, P5 null] gives P4.
     *
     * @param array<int, MtQueueLoad> $loads
     */
    private static function lightest(array $loads): ?MtQueueLoad
    {
        $lightest = null;
        foreach ($loads as $load) {
            if ($lightest === null || self::isLighter($load, $lightest)) {
                $lightest = $load;
            }
        }

        return $lightest;
    }

    /**
     * Tells whether the candidate has a known load lower than the current one.
     *
     * Example: candidate 100 against current null gives true; candidate null against current 100 gives false.
     */
    private static function isLighter(MtQueueLoad $candidate, MtQueueLoad $current): bool
    {
        if ($candidate->segmentLoad === null) {
            return false;
        }

        return $current->segmentLoad === null || $candidate->segmentLoad < $current->segmentLoad;
    }
}
