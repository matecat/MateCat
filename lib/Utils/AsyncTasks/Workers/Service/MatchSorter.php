<?php

namespace Utils\AsyncTasks\Workers\Service;

use Model\Analysis\Constants\InternalMatchesConstants;
use Utils\AsyncTasks\Workers\Interface\MatchSorterInterface;

class MatchSorter implements MatchSorterInterface
{
    /**
     * @param array<string, mixed> $match
     */
    public function isMtMatch(array $match): bool
    {
        return stripos($match['created_by'] ?? '', InternalMatchesConstants::MT) !== false;
    }

    /**
     * @param array<string, mixed>              $mtResult
     * @param array<int, array<string, mixed>>  $tmMatches
     *
     * @return list<array<string, mixed>>
     */
    public function sortMatches(array $mtResult, array $tmMatches): array
    {
        if (!empty($mtResult)) {
            $tmMatches[] = $mtResult;
        }

        usort($tmMatches, $this->compareScoreDesc(...));

        return $tmMatches;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function compareScoreDesc(array $a, array $b): int
    {
        $aIsICE = (bool)($a['ICE'] ?? false);
        $bIsICE = (bool)($b['ICE'] ?? false);

        $aMatch = floatval($a['match']);
        $bMatch = floatval($b['match']);

        if ($aMatch == $bMatch) {
            $conditions = [
                [$aIsICE && !$bIsICE, -1],
                [!$aIsICE && $bIsICE, 1],
                [$this->isMtMatch($a) && !$this->isMtMatch($b), -1],
                [!$this->isMtMatch($a) && $this->isMtMatch($b), 1]
            ];

            foreach ($conditions as [$condition, $result]) {
                if ($condition) {
                    return $result;
                }
            }

            return 0;
        }

        return ($aMatch < $bMatch ? 1 : -1);
    }
}
