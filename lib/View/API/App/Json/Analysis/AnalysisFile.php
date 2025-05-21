<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:11
 *
 */

namespace API\App\Json\Analysis;

use JsonSerializable;
use Model\Analysis\Constants\ConstantsInterface;

class AnalysisFile implements MatchContainerInterface, JsonSerializable {

    /**
     * @var int
     */
    protected int $id;
    /**
     * @var string
     */
    protected string $name = "";

    /**
     * @var AnalysisMatch[]
     */
    protected array $matches = [];
    /**
     * @var string
     */
    protected string $original_name;
    /**
     * @var string|null
     */
    protected ?string $id_file_part;

    /**
     * @var int
     */
    protected int $total_raw = 0;
    /**
     * @var int
     */
    protected int $total_equivalent = 0;

    public function __construct( $id, $id_file_part, $name, $original_name, ConstantsInterface $matchConstantsClass ) {
        $this->id            = (int)$id;
        $this->id_file_part  = $id_file_part;
        $this->name          = $name;
        $this->original_name = $original_name;
        foreach ( $matchConstantsClass::forValue() as $matchType ) {
            $this->matches[ $matchType ] = AnalysisMatch::forName( $matchType, $matchConstantsClass );
        }

    }

    public function jsonSerialize(): array {
        return [
                'id'               => $this->id,
                'id_file_part'     => $this->id_file_part,
                'name'             => $this->name,
                'original_name'    => $this->original_name,
                'total_raw'        => $this->total_raw,
                'total_equivalent' => $this->total_equivalent,
                'matches'          => array_values( $this->matches )
        ];
    }

    /**
     * @return int
     */
    public function getId(): int {
        return $this->id;
    }

    /**
     * @param string $matchName
     *
     * @return AnalysisMatch
     */
    public function getMatch( string $matchName ): AnalysisMatch {
        return $this->matches[ $matchName ];
    }

    /**
     * @return string
     */
    public function getName(): string {
        return $this->name;
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

}