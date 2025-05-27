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
use Model\Analysis\Constants\ConstantsInterface;

class AnalysisMatch implements JsonSerializable {

    /**
     * @var int
     */
    protected int $raw = 0;
    /**
     * @var float
     */
    protected float $equivalent = 0;

    /**
     * @var string
     */
    protected string $type;

    public static function forName( string $name, ConstantsInterface $matchConstantsClass ): AnalysisMatch {
        return new static( $name, $matchConstantsClass );
    }

    /**
     * @param string             $name
     * @param ConstantsInterface $matchConstantsClass
     */
    protected function __construct( string $name, ConstantsInterface $matchConstantsClass ) {
        $this->type = $matchConstantsClass::validate( $name );
    }

    public function jsonSerialize(): array {
        return [
                'raw'        => $this->raw,
                'equivalent' => round( $this->equivalent ),
                'type'       => $this->type
        ];
    }

    public function name(): string {
        return $this->type;
    }

    /**
     * @param int $raw
     *
     * @return $this
     */
    public function setRaw( int $raw ): AnalysisMatch {
        $this->raw = $raw;

        return $this;
    }

    /**
     * @param float $equivalent
     *
     * @return $this
     */
    public function setEquivalent( float $equivalent ): AnalysisMatch {
        $this->equivalent = $equivalent;

        return $this;
    }

    /**
     * @param int $raw
     *
     * @return void
     */
    public function incrementRaw( int $raw ) {
        $this->raw += $raw;
    }

    /**
     * @param float $equivalent
     *
     * @return void
     */
    public function incrementEquivalent( float $equivalent ) {
        $this->equivalent += $equivalent;
    }

    /**
     * @return int
     */
    public function getRaw(): int {
        return $this->raw;
    }

    /**
     * @return float
     */
    public function getEquivalent(): float {
        return $this->equivalent;
    }

}