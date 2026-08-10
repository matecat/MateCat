<?php

namespace Model\Projects;

/**
 * The outcome of a project count that stops early instead of scanning a whole team.
 *
 * An exact total costs one row read per project in the team, and the largest teams hold hundreds of
 * thousands of them, so the price grows forever while nobody reads the figure past its first digits.
 * The counting queries therefore stop once they have seen one row more than the cap, and report here
 * whether they hit it, so a caller can render "10000+" rather than pay for the real number.
 */
final class ProjectsCount
{
    /**
     * One more row than this is fetched, so that reaching the cap is distinguishable from landing
     * exactly on it.
     */
    public const int DEFAULT_CAP = 10000;

    private function __construct(
        public readonly int  $value,
        public readonly bool $approximated
    ) {
    }

    /**
     * @param int $counted rows returned by the capped query, at most $cap + 1
     * @param int $cap     the ceiling the query was built with
     */
    public static function fromCappedQuery(int $counted, int $cap = self::DEFAULT_CAP): self
    {
        return $counted > $cap ? new self($cap, true) : new self($counted, false);
    }

    /**
     * The number of rows a capped query has to ask for to make {@see fromCappedQuery} able to tell
     * "there are more" from "there are exactly this many".
     */
    public static function queryLimit(int $cap = self::DEFAULT_CAP): int
    {
        return $cap + 1;
    }

    /**
     * Display form: "10000+" once the count is approximated.
     */
    public function toString(): string
    {
        return $this->approximated ? $this->value . '+' : (string)$this->value;
    }
}
