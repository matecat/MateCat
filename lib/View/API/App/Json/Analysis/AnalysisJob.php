<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:10
 *
 */

namespace API\App\Json\Analysis;

use CatUtils;
use Exception;
use JsonSerializable;
use Langs_Languages;

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
    /**
     * @var string
     */
    protected $sourceName;
    /**
     * @var string
     */
    protected $targetName;
    /**
     * @var bool
     */
    protected $outsourceAvailable = true;

    /**
     * @var array
     */
    protected $payable_rates = [];

    public function jsonSerialize() {
        return [
                'id'                  => $this->id,
                'source'              => $this->source,
                'source_name'         => $this->sourceName,
                'target'              => $this->target,
                'target_name'         => $this->targetName,
                'chunks'              => array_values( $this->chunks ),
                'total_raw'           => $this->total_raw,
                'total_equivalent'    => round( $this->total_equivalent ),
                'total_industry'      => round( $this->total_industry ),
                'outsource_available' => $this->outsourceAvailable,
                'payable_rates'       => $this->payable_rates,
                'count_unit'          => $this->getCountUnit( $this->source ),
        ];
    }

    /**
     * @param int    $id
     * @param string $source
     * @param string $target
     *
     * @throws Exception
     */
    public function __construct( $id, $source, $target ) {
        $this->id         = (int)$id;
        $this->source     = $source;
        $this->target     = $target;
        $lang_handler     = Langs_Languages::getInstance();
        $this->sourceName = $lang_handler->getLocalizedName( $source );
        $this->targetName = $lang_handler->getLocalizedName( $target );
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
        $this->total_equivalent += $equivalent;
    }

    /**
     * @param $industry
     *
     * @return void
     */
    public function incrementIndustry( $industry ) {
        $this->total_industry += $industry;
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

    /**
     * @return string
     */
    public function getLangPair()
    {
        return $this->source."|".$this->target;
    }

    /**
     * @param bool $outsourceAvailable
     *
     * @return $this
     */
    public function setOutsourceAvailable( $outsourceAvailable ) {
        $this->outsourceAvailable = $outsourceAvailable;

        return $this;
    }

    /**
     * @param array $payable_rates
     *
     * @return $this
     */
    public function setPayableRates( $payable_rates ) {
        $this->payable_rates = $payable_rates;

        return $this;
    }

    private function getCountUnit( $languageCode ) {
        return array_key_exists( explode( "-", $languageCode )[ 0 ], CatUtils::$cjk ) ? 'characters' : 'words';
    }
}