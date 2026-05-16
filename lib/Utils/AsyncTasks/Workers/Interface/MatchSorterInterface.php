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
     * @param array<string, mixed>              $mtResult
     * @param array<int, array<string, mixed>>  $tmMatches
     *
     * @return list<array<string, mixed>>
     */
    public function sortMatches(array $mtResult, array $tmMatches): array;
}
