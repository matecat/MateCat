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
     * @var string
     */
    protected $projectName;
    /**
     * @var string
     */
    protected $source;
    /**
     * @var string
     */
    protected $target;

    public function jsonSerialize() {
        return [
                'id'               => $this->id,
                'source'           => $this->source,
                'target'           => $this->target,
                'chunks'           => array_values( $this->chunks ),
                'total_raw'        => $this->total_raw,
                'total_equivalent' => $this->total_equivalent,
                'total_industry'   => $this->total_industry,
        ];
    }

    /**
     * @param int $id
     * @param string $source
     * @param string $target
     */
    public function __construct( $id, $source, $target ) {
        $this->id     = (int)$id;
        $this->source = $source;
        $this->target = $target;
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
     * @return AnalysisChunk[]
     */
    public function getChunks() {
        return $this->chunks;
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
     * @return string
     */
    public function getSource() {
        return $this->source;
    }

    /**
     * @return string
     */
    public function getTarget() {
        return $this->target;
    }

}