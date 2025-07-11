<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/04/25
 * Time: 18:47
 *
 */

namespace Model\MTQE\PayableRate;

use DomainException;
use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\MTQE\PayableRate\DTO\MTQEPayableRateBreakdowns;

class MTQEPayableRateStruct extends AbstractDaoSilentStruct implements JsonSerializable {

    public int     $id          = 0;
    public string  $name        = "";
    public int     $uid         = 0;
    public int     $version     = 1;
    public ?string $created_at  = null;
    public ?string $modified_at = null;
    public ?string $deleted_at  = null;

    /**
     * @var MTQEPayableRateBreakdowns|null
     */
    public ?MTQEPayableRateBreakdowns $breakdowns = null;

    /**
     * @param string $json
     * @param null   $uid
     *
     * @return $this
     */
    public function hydrateFromJSON( string $json, $uid = null ): MTQEPayableRateStruct {

        $decoded_json = json_decode( $json, true );

        if ( !isset( $decoded_json[ 'name' ] ) ) {
            throw new DomainException( "Cannot instantiate a new MTQEPayableRateStruct. Invalid data provided.", 400 );
        }

        if ( empty( $uid ) && empty( $decoded_json[ 'uid' ] ) ) {
            throw new DomainException( "Cannot instantiate a new MTQEPayableRateStruct. Invalid user id provided.", 400 );
        }

        $this->uid  = $decoded_json[ 'uid' ] ?? $uid;
        $this->name = $decoded_json[ 'name' ];

        if ( isset( $decoded_json[ 'id' ] ) ) {
            $this->id = $decoded_json[ 'id' ];
        }

        if ( isset( $decoded_json[ 'version' ] ) ) {
            $this->version = $decoded_json[ 'version' ];
        }

        if ( isset( $decoded_json[ 'created_at' ] ) ) {
            $this->created_at = $decoded_json[ 'created_at' ];
        }

        if ( isset( $decoded_json[ 'deleted_at' ] ) ) {
            $this->deleted_at = $decoded_json[ 'deleted_at' ];
        }

        if ( isset( $decoded_json[ 'modified_at' ] ) ) {
            $this->modified_at = $decoded_json[ 'modified_at' ];
        }

        // params
        if ( isset( $decoded_json[ 'breakdowns' ] ) ) {
            ( is_string( $decoded_json[ 'breakdowns' ] ) ) ? $this->hydrateBreakdownsFromJson( $decoded_json[ 'breakdowns' ] ) : $this->hydrateBreakdownsFromDataArray( $decoded_json[ 'breakdowns' ] );
        }

        return $this;

    }

    /**
     * @param string $jsonParams
     *
     * @return MTQEPayableRateStruct
     */
    public function hydrateBreakdownsFromJson( string $jsonParams ): MTQEPayableRateStruct {
        $rules = json_decode( $jsonParams, true );

        return $this->hydrateBreakdownsFromDataArray( $rules );
    }

    public function hydrateBreakdownsFromDataArray( array $params ): MTQEPayableRateStruct {

        $this->breakdowns = new MTQEPayableRateBreakdowns();

        // rules
        if ( isset( $params[ 'breakdowns' ] ) ) {
            $this->breakdowns = new MTQEPayableRateBreakdowns( $params[ 'breakdowns' ] );
        }

        return $this;

    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return $this->getArrayCopy();
    }

    public function __toString(): string {
        return json_encode( $this->jsonSerialize() );
    }

}