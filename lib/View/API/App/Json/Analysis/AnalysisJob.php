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
use Langs\Languages;
use stdClass;

class AnalysisJob implements JsonSerializable {

    /**
     * @var int
     */
    protected int $id;
    /**
     * @var AnalysisChunk[]
     */
    protected array $chunks = [];

    /**
     * @var int
     */
    protected int $total_raw = 0;
    /**
     * @var int
     */
    protected int $total_equivalent = 0;
    /**
     * @var float
     */
    protected float $total_industry = 0;
    /**
     * @var string
     */
    protected string $projectName;
    /**
     * @var string
     */
    protected string $source;
    /**
     * @var string
     */
    protected string $target;
    /**
     * @var string
     */
    protected string $sourceName;
    /**
     * @var string
     */
    protected string $targetName;
    /**
     * @var bool
     */
    protected bool $outsourceAvailable = true;

    /**
     * @var object
     */
    protected object $payable_rates;

    /**
     * @return array
     */
    public function jsonSerialize(): array {
        return [
                'id'                  => $this->id,
                'source'              => $this->source,
                'source_name'         => $this->sourceName,
                'target'              => $this->target,
                'target_name'         => $this->targetName,
                'chunks'              => array_values( $this->chunks ),
                'total_raw'           => $this->total_raw,
                'total_equivalent'    => $this->total_equivalent,
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
    public function __construct( int $id, string $source, string $target ) {
        $this->id            = $id;
        $this->source        = $source;
        $this->target        = $target;
        $lang_handler        = Languages::getInstance();
        $this->sourceName    = $lang_handler->getLocalizedName( $source );
        $this->targetName    = $lang_handler->getLocalizedName( $target );
        $this->payable_rates = new stdClass();
    }

    /**
     * @param AnalysisChunk $chunk
     *
     * @return $this
     */
    public function setChunk( AnalysisChunk $chunk ): AnalysisJob {
        $this->chunks [ $chunk->getPassword() ] = $chunk;

        return $this;
    }

    /**
     * @return AnalysisChunk[]
     */
    public function getChunks(): array {
        return $this->chunks;
    }

    /**
     * @param string $password
     *
     * @return bool
     */
    public function hasChunk( string $password ): bool {
        return array_key_exists( $password, $this->chunks );
    }

    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * @param int $raw
     *
     * @return void
     */
    public function incrementRaw( int $raw ) {
        $this->total_raw += $raw;
    }

    /**
     * @param float $equivalent
     *
     * @return void
     */
    public function incrementEquivalent( float $equivalent ) {
        $this->total_equivalent += round( $equivalent );
    }

    /**
     * @param float $industry
     *
     * @return void
     */
    public function incrementIndustry( float $industry ) {
        $this->total_industry += $industry;
    }

    /**
     * @return string
     */
    public function getSource(): string {
        return $this->source;
    }

    /**
     * @return string
     */
    public function getTarget(): string {
        return $this->target;
    }

    /**
     * @return string
     */
    public function getLangPair(): string {
        return $this->source . "|" . $this->target;
    }

    /**
     * @param bool $outsourceAvailable
     *
     * @return $this
     */
    public function setOutsourceAvailable( bool $outsourceAvailable ): AnalysisJob {
        $this->outsourceAvailable = $outsourceAvailable;

        return $this;
    }

    /**
     * @param object $payable_rates
     *
     * @return $this
     */
    public function setPayableRates( object $payable_rates ): AnalysisJob {
        $this->payable_rates = $payable_rates;

        return $this;
    }

    private function getCountUnit( string $languageCode ): string {
        return array_key_exists( explode( "-", $languageCode )[ 0 ], CatUtils::$cjk ) ? 'characters' : 'words';
    }
}