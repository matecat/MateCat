<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:11
 *
 */

namespace View\API\App\Json\Analysis;

use JsonSerializable;
use Model\Analysis\Constants\ConstantsInterface;

class AnalysisJobSummary implements MatchContainerInterface, JsonSerializable
{

    /**
     * @var AnalysisMatch[]
     */
    protected array $matches = [];

    public function __construct(ConstantsInterface $matchConstantsClass)
    {
        foreach ($matchConstantsClass::forValue() as $matchType) {
            $this->matches[$matchType] = AnalysisMatch::forName($matchType, $matchConstantsClass);
        }
    }

    public function jsonSerialize(): array
    {
        return array_values($this->matches);
    }

    /**
     * @param string $matchName
     *
     * @return AnalysisMatch
     */
    public function getMatch(string $matchName): AnalysisMatch
    {
        return $this->matches[$matchName];
    }

}