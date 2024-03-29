<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:12
 *
 */

namespace API\App\Json\Analysis;

use JsonSerializable;
use RuntimeException;

class AnalysisMatch implements JsonSerializable {

    /**
     * @var int
     */
    protected $raw = 0;
    /**
     * @var int
     */
    protected $equivalent = 0;

    /**
     * @var string
     */
    protected $type = null;

    public static function forName( $name ) {
        return new static( $name );
    }

    /**
     * @param $name
     *
     * @throws RuntimeException
     */
    protected function __construct( $name ) {
        $this->type = MatchConstants::validate( $name );
    }

    public function jsonSerialize() {
        return [
                'raw'        => $this->raw,
                'equivalent' => round( $this->equivalent ),
                'type'       => $this->type
        ];
    }

    public function name() {
        return $this->type;
    }

    /**
     * @param int $raw
     *
     * @return $this
     */
    public function setRaw( $raw ) {
        $this->raw = (int)$raw;

        return $this;
    }

    /**
     * @param float $equivalent
     *
     * @return $this
     */
    public function setEquivalent( $equivalent ) {
        $this->equivalent = $equivalent;

        return $this;
    }

    /**
     * @param $raw
     *
     * @return void
     */
    public function incrementRaw( $raw ) {
        $this->raw += (int)$raw;
    }

    /**
     * @param $equivalent
     *
     * @return void
     */
    public function incrementEquivalent( $equivalent ) {
        $this->equivalent += $equivalent;
    }

    /**
     * @return int
     */
    public function getRaw() {
        return $this->raw;
    }

    /**
     * @return int
     */
    public function getEquivalent() {
        return $this->equivalent;
    }

}