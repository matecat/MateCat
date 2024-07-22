<?php

namespace Xliff;

use DataAccess_AbstractDaoSilentStruct;
use Date\DateTimeUtil;
use DomainException;
use Exception;
use JsonSerializable;
use stdClass;
use Xliff\DTO\Xliff12Rule;
use Xliff\DTO\Xliff20Rule;
use Xliff\DTO\XliffRulesModel;

class XliffConfigTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements JsonSerializable {

    public ?int    $id          = null;
    public string  $name;
    public int     $uid;
    public ?string $created_at  = null;
    public ?string $modified_at = null;
    public ?string $deleted_at  = null;

    /**
     * @var XliffRulesModel|null
     */
    public ?XliffRulesModel $rules = null;

    /**
     * @param      $json
     * @param null $uid
     *
     * @return $this
     */
    public function hydrateFromJSON( $json, $uid = null ): XliffConfigTemplateStruct {

        $json  = json_decode( $json, true );
        $rules = ( is_array( $json[ 'rules' ] ) ) ? $json[ 'rules' ] : json_decode( $json[ 'rules' ], true );

        if ( !isset( $json[ 'name' ] ) ) {
            throw new DomainException( "Cannot instantiate a new XliffConfigStruct. Invalid data provided.", 400 );
        }

        if ( empty( $uid ) && empty( $json[ 'uid' ] ) ) {
            throw new DomainException( "Cannot instantiate a new XliffConfigStruct. Invalid user id provided.", 400 );
        }

        $this->uid  = $json[ 'uid' ] ?? $uid;
        $this->name = $json[ 'name' ];

        if ( isset( $json[ 'id' ] ) ) {
            $this->id = $json[ 'id' ];
        }

        if ( isset( $json[ 'created_at' ] ) ) {
            $this->created_at = $json[ 'created_at' ];
        }

        if ( isset( $json[ 'deleted_at' ] ) ) {
            $this->deleted_at = $json[ 'deleted_at' ];
        }

        if ( isset( $json[ 'modified_at' ] ) ) {
            $this->modified_at = $json[ 'modified_at' ];
        }

        $this->rules = new XliffRulesModel();

        // rules
        if ( isset( $json[ 'rules' ] ) ) {

            if ( isset( $rules[ 'xliff12' ] ) and is_array( $rules[ 'xliff12' ] ) ) {
                foreach ( $rules[ 'xliff12' ] as $xliff12Rule ) {
                    $rule = new Xliff12Rule( $xliff12Rule[ 'states' ], $xliff12Rule[ 'analysis' ], $xliff12Rule[ 'editor' ], $xliff12Rule[ 'match_category' ] ?? null );
                    $this->rules->addRule( $rule );
                }
            }

            // xliff20
            if ( isset( $rules[ 'xliff20' ] ) and is_array( $rules[ 'xliff20' ] ) ) {
                foreach ( $rules[ 'xliff20' ] as $xliff20Rule ) {
                    $rule = new Xliff20Rule( $xliff20Rule[ 'states' ], $xliff20Rule[ 'analysis' ], $xliff20Rule[ 'editor' ], $xliff20Rule[ 'match_category' ] ?? null );
                    $this->rules->addRule( $rule );
                }
            }
        }

        return $this;

    }

    /**
     * @return array
     * @throws Exception
     */
    public function jsonSerialize(): array {

        return [
                'id'         => $this->id,
                'uid'        => $this->uid,
                'name'       => $this->name,
                'rules'      => $this->rules ?? new stdClass(),
                'createdAt'  => DateTimeUtil::formatIsoDate( $this->created_at ),
                'modifiedAt' => DateTimeUtil::formatIsoDate( $this->modified_at ),
                'deletedAt'  => DateTimeUtil::formatIsoDate( $this->deleted_at ),
        ];
    }
}
