<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:10
 *
 */

namespace API\App\Json\Analysis;

use JsonSerializable;

class AnalysisJob implements JsonSerializable {

    /**
     * @var int
     */
    protected $id = null;
    /**
     * @var AnalysisChunk[]
     */
    protected $chunks = [];

    /**
     * @var AnalysisJobSummary
     */
    protected $summary = null;

    /**
     * @var int
     */
    protected $total_raw = 0;
    /**
     * @var int
     */
    protected $total_equivalent = 0;
    /**
     * @var int
     */
    protected $total_industry = 0;
    /**
     * @var bool
     */
    protected $outsource = true;

    public function jsonSerialize() {
        return [
                'id'               => $this->id,
                'chunks'           => array_values( $this->chunks ),
                'summary'          => $this->summary,
                'total_raw'        => $this->total_raw,
                'total_equivalent' => $this->total_equivalent,
                'total_industry'   => $this->total_industry,
                'outsource'        => $this->outsource
        ];
    }

    public function __construct( $id ) {
        $this->id      = $id;
        $this->summary = new AnalysisJobSummary();
    }

    /**
     * @param AnalysisChunk $chunk
     *
     * @return $this
     */
    public function setChunk( AnalysisChunk $chunk ) {
        $this->chunks [ $chunk->getPassword() ] = $chunk;

        return $this;
    }

    /**
     * @param $password
     *
     * @return bool
     */
    public function hasChunk( $password ) {
        return array_key_exists( $password, $this->chunks );
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return AnalysisJobSummary
     */
    public function getSummary() {
        return $this->summary;
    }

    /**
     * @param $raw
     *
     * @return void
     */
    public function incrementRaw( $raw ) {
        $this->total_raw += (int)$raw;
    }

    /**
     * @param $equivalent
     *
     * @return void
     */
    public function incrementEquivalent( $equivalent ) {
        $this->total_equivalent += round( $equivalent );
    }

    /**
     * @param $industry
     *
     * @return void
     */
    public function incrementIndustry( $industry ) {
        $this->total_industry += round( $industry );
    }

    /**
     * @param bool $outsource
     *
     * @return $this
     */
    public function setOutsource( $outsource ) {
        $this->outsource = $outsource;
        return $this;
    }

}