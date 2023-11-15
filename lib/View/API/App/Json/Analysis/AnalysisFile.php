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

class AnalysisFile implements JsonSerializable {

    /**
     * @var int
     */
    protected $id = null;

    /**
     * @var string
     */
    protected $name = "";

    /**
     * @var AnalysisMatch[]
     */
    protected $matches = [];

    public function __construct( $id, $name ) {
        $this->id   = $id;
        $this->name = $name;
        foreach ( MatchConstants::$forValue as $matchType ) {
            $this->matches[ $matchType ] = AnalysisMatch::forName( $matchType );
        }
    }

    public function jsonSerialize() {
        return [
                'id'      => $this->id,
                'name'    => $this->name,
                'matches' => array_values( $this->matches )
        ];
    }

    /**
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return AnalysisMatch
     */
    public function getMatch( $matchName ) {
        return $this->matches[ $matchName ];
    }


}