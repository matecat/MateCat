<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/04/25
 * Time: 18:47
 *
 */

namespace MTQE\Templates;

use DataAccess_AbstractDaoSilentStruct;
use DomainException;
use JsonSerializable;
use MTQE\Templates\DTO\MTQEWorkflowParams;

class MTQEWorkflowTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements JsonSerializable {

    public int     $id          = 0;
    public string  $name        = "";
    public int     $uid         = 0;
    public ?string $created_at  = null;
    public ?string $modified_at = null;
    public ?string $deleted_at  = null;

    /**
     * @var MTQEWorkflowParams|null
     */
    public ?MTQEWorkflowParams $params = null;

    /**
     * @param string $json
     * @param null   $uid
     *
     * @return $this
     */
    public function hydrateFromJSON( string $json, $uid = null ): MTQEWorkflowTemplateStruct {

        $decoded_json = json_decode( $json, true );

        if ( !isset( $decoded_json[ 'name' ] ) ) {
            throw new DomainException( "Cannot instantiate a new MTQEWorkflowTemplateStruct. Invalid data provided.", 400 );
        }

        if ( empty( $uid ) && empty( $decoded_json[ 'uid' ] ) ) {
            throw new DomainException( "Cannot instantiate a new MTQEWorkflowTemplateStruct. Invalid user id provided.", 400 );
        }

        $this->uid  = $decoded_json[ 'uid' ] ?? $uid;
        $this->name = $decoded_json[ 'name' ];

        if ( isset( $decoded_json[ 'id' ] ) ) {
            $this->id = $decoded_json[ 'id' ];
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
        if ( isset( $decoded_json[ 'params' ] ) ) {
            ( is_string( $decoded_json[ 'params' ] ) ) ? $this->hydrateParamsFromJson( $decoded_json[ 'params' ] ) : $this->hydrateParamsFromDataArray( $decoded_json[ 'params' ] );
        }

        return $this;

    }

    /**
     * @param string $jsonParams
     *
     * @return MTQEWorkflowTemplateStruct
     */
    public function hydrateParamsFromJson( string $jsonParams ): MTQEWorkflowTemplateStruct {
        $rules = json_decode( $jsonParams, true );

        return $this->hydrateParamsFromDataArray( $rules );
    }

    public function hydrateParamsFromDataArray( array $params ): MTQEWorkflowTemplateStruct {

        $this->params = new MTQEWorkflowParams();

        // rules
        if ( isset( $params[ 'params' ] ) ) {
            $this->params = new MTQEWorkflowParams( $params[ 'params' ] );
        }

        return $this;

    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return (array) $this;
    }

    public function __toString(): string {
        return json_encode( $this->jsonSerialize() );
    }

}