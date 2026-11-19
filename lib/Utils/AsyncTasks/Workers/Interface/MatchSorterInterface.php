<?php

namespace Utils\AsyncTasks\Workers\Interface;

interface MatchSorterInterface
{
    /**
     * @param array<string, mixed> $match
     */
    public function isMtMatch(array $match): bool;

    /**
     * Appends the MT result (if non-empty) to the TM matches and sorts
     * them descending by score, with ICE and MT tiebreakers.
     *
     * When $limit is given, at most $limit matches are returned and one slot is reserved for
     * the MT result, so that it survives the cut even when every TM match outscores it.
     *
     * @param array<string, mixed>              $mtResult
     * @param array<int, array<string, mixed>>  $tmMatches
     * @param int|null                          $limit
     *
     * @return list<array<string, mixed>>
     */
    public function sortMatches(array $mtResult, array $tmMatches, ?int $limit = null): array;
}
