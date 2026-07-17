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
use Utils\Constants\ProjectStatus;

class AnalysisProjectSummary implements JsonSerializable
{

    /**
     * @var int
     */
    protected int $in_queue_before = 0;
    /**
     * @var int
     */
    protected int $segments_analyzed = 0;
    /**
     * @var int
     */
    protected int $total_segments = 0;
    /**
     * @var string
     */
    protected string $analysis_status = ProjectStatus::STATUS_NEW;
    /**
     * @var float
     */
    protected float $total_equivalent = 0;
    /**
     * @var int
     */
    protected int $total_raw = 0;
    /**
     * @var float
     */
    protected float $total_industry = 0;
    /**
     * @var int
     */
    protected int $discount = 0;
    /**
     * @var int
     */
    protected int $total_fast_analysis = 0;

    /**
     * @param int $in_queue_before
     * @param int $total_segments
     * @param string $analysis_status
     */
    public function __construct(int $in_queue_before, int $total_segments, string $analysis_status)
    {
        $this->in_queue_before = $in_queue_before;
        $this->total_segments = $total_segments;
        $this->analysis_status = $analysis_status;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'in_queue_before' => $this->in_queue_before,
            'total_segments' => $this->total_segments,
            'segments_analyzed' => $this->segments_analyzed,
            'status' => $this->analysis_status,
            'total_raw' => $this->total_raw,
            'total_industry' => max(round($this->total_industry), round($this->total_equivalent)),
            'total_equivalent' => round($this->total_equivalent),
            'discount' => $this->getDiscount()
        ];
    }

    /**
     * @return float
     */
    public function getDiscount(): float
    {
        if (empty($this->total_raw)) {
            return 0;
        }

        return round((($this->total_raw - round($this->total_equivalent)) / $this->total_raw) * 100);
    }

    /**
     * @param int $total_fast_analysis
     *
     * @return $this
     */
    public function setTotalFastAnalysis(int $total_fast_analysis): AnalysisProjectSummary
    {
        $this->total_fast_analysis = $total_fast_analysis;

        return $this;
    }

    /**
     * @return int
     */
    public function getTotalFastAnalysis(): int
    {
        return $this->total_fast_analysis;
    }

    /**
     * @return void
     */
    public function incrementAnalyzed(): void
    {
        $this->segments_analyzed++;
    }

    /**
     * @param float $equivalent
     *
     * @return void
     */
    public function incrementEquivalent(float $equivalent): void
    {
        $this->total_equivalent += $equivalent;
    }

    /**
     * @param int $raw
     *
     * @return void
     */
    public function incrementRaw(int $raw): void
    {
        $this->total_raw += $raw;
    }

    /**
     * @param float $industry
     *
     * @return void
     */
    public function incrementIndustry(float $industry): void
    {
        $this->total_industry += $industry;
    }

    /**
     * @return int
     */
    public function getSegmentsAnalyzed(): int
    {
        return $this->segments_analyzed;
    }

    /**
     * @return int
     */
    public function getTotalSegments(): int
    {
        return $this->total_segments;
    }

}