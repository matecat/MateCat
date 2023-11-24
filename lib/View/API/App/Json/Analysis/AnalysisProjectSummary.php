<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:11
 *
 */

namespace API\App\Json\Analysis;

use Constants_ProjectStatus;
use INIT;
use JsonSerializable;

class AnalysisProjectSummary implements JsonSerializable {

    /**
     * @var int
     */
    protected $in_queue_before = 0;
    /**
     * @var int
     */
    protected $segments_analyzed = 0;
    /**
     * @var int
     */
    protected $total_segments = 0;
    /**
     * @var string
     */
    protected $analysis_status = Constants_ProjectStatus::STATUS_NEW;
    /**
     * @var int
     */
    protected $total_equivalent = 0;
    /**
     * @var int
     */
    protected $total_raw = 0;
    /**
     * @var int
     */
    protected $total_industry = 0;
    /**
     * @var int
     */
    protected $discount = 0;
    /**
     * @var int
     */
    protected $total_fast_analysis;

    /**
     * @param int    $in_queue_before
     * @param int    $total_segments
     * @param string $analysis_status
     */
    public function __construct( $in_queue_before, $total_segments, $analysis_status ) {
        $this->in_queue_before = $in_queue_before;
        $this->total_segments  = $total_segments;
        $this->analysis_status = $analysis_status;
    }

    public function jsonSerialize() {
        return [
                'in_queue_before'   => $this->in_queue_before,
                'total_segments'    => $this->total_segments,
                'segments_analyzed' => $this->segments_analyzed,
                'status'            => $this->analysis_status,
                'total_raw'         => $this->total_raw,
                'total_industry'    => round( $this->total_industry ),
                'total_equivalent'  => round( $this->total_equivalent ),
                'discount'          => $this->getDiscount()
        ];
    }

    private function getEstimatedWorkTime() {

        $wc_time = $this->total_equivalent / INIT::$ANALYSIS_WORDS_PER_DAYS;
        $wc_unit = 'day';

        if ( $wc_time > 0 and $wc_time < 1 ) {
            $wc_time *= 8; //convert to hours (1 work day = 8 hours)
            $wc_unit = 'hour';
        }

        if ( $wc_time > 0 and $wc_time < 1 ) {
            $wc_time *= 60; //convert to minutes
            $wc_unit = 'minute';
        }

        if ( $wc_time > 1 ) {
            $wc_unit .= 's';
        }

        return number_format( round( $wc_time ) ) . " work " . $wc_unit;

    }

    public function getDiscount() {
        if ( empty( $this->total_raw ) ) {
            return 0;
        }
        return round( ( ( $this->total_raw - round( $this->total_equivalent ) ) / $this->total_raw ) * 100 );
    }

    /**
     * @param int $total_fast_analysis
     *
     * @return $this
     */
    public function setTotalFastAnalysis( $total_fast_analysis ) {
        $this->total_fast_analysis = (int)$total_fast_analysis;

        return $this;
    }

    /**
     * @return int
     */
    public function getTotalFastAnalysis() {
        return $this->total_fast_analysis;
    }

    /**
     * @return void
     */
    public function incrementAnalyzed() {
        $this->segments_analyzed++;
    }

    /**
     * @return void
     */
    public function incrementEquivalent( $equivalent ) {
        $this->total_equivalent += $equivalent;
    }

    /**
     * @return void
     */
    public function incrementRaw( $raw ) {
        $this->total_raw += $raw;
    }

    /**
     * @return void
     */
    public function incrementIndustry( $industry ) {
        $this->total_industry += $industry;
    }

    /**
     * @return int
     */
    public function getTotalIndustry() {
        return $this->total_industry;
    }

    /**
     * @return int
     */
    public function getSegmentsAnalyzed() {
        return $this->segments_analyzed;
    }

    /**
     * @return int
     */
    public function getTotalEquivalent() {
        return $this->total_equivalent;
    }

    /**
     * @return int
     */
    public function getTotalSegments() {
        return $this->total_segments;
    }

}