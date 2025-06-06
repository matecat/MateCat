<?php

namespace PayableRates;

use Analysis\PayableRates;
use \DataAccess\AbstractDaoSilentStruct;
use \DataAccess\IDaoStruct;
use Date\DateTimeUtil;
use DomainException;
use Exception;
use JsonSerializable;
use Langs\Languages;

class CustomPayableRateStruct extends \DataAccess\AbstractDaoSilentStruct implements \DataAccess\IDaoStruct, JsonSerializable {
    const MAX_BREAKDOWN_SIZE = 65535;

    public $id;
    public $uid;
    public $version;
    public $name;
    public $breakdowns;
    public $created_at;
    public $modified_at;
    public $deleted_at;

    /**
     * @return string
     */
    public function breakdownsToJson(): string {
        return json_encode( $this->getBreakdownsArray() );
    }

    /**
     * @return array
     */
    protected function getBreakdownsArray(): array {
        $this->breakdowns = ( is_string( $this->breakdowns ) ? json_decode( $this->breakdowns, true ) : $this->breakdowns );

        // WARNING: backward compatibility for old data stored, they could not have ICE_MT
        foreach ( $this->breakdowns as $sourceLang => $targetLanguages ) {
            if ( $sourceLang == 'default' ) {
                continue;
            }
            foreach ( $targetLanguages as $targetLanguage => $targetPayableRates ) {
                if ( !isset( $targetPayableRates[ 'ICE_MT' ] ) ) {
                    $this->breakdowns[ $sourceLang ][ $targetLanguage ][ 'ICE_MT' ] = $targetPayableRates[ 'MT' ];
                }
            }
        }

        return $this->breakdowns;
    }

    /**
     * @param string $source
     * @param string $target
     *
     * @return array
     */
    public function getPayableRates( string $source, string $target ): array {

        $breakdowns = $this->getBreakdownsArray();

        $this->validateLanguage( $source );
        $this->validateLanguage( $target );

        return PayableRates::resolveBreakdowns( $breakdowns, $source, $target, $breakdowns[ 'default' ] );

    }

    /**
     * @param string $json
     *
     * @return $this
     *
     * @throws Exception
     */
    public function hydrateFromJSON( string $json ): CustomPayableRateStruct {
        $json = json_decode( $json, true );

        if (
                !isset( $json[ 'payable_rate_template_name' ] ) and
                !isset( $json[ 'breakdowns' ] )
        ) {
            throw new Exception( "Cannot instantiate a new CustomPayableRateStruct. Invalid JSON provided.", 403 );
        }

        $this->validateBreakdowns( $json[ 'breakdowns' ] );

        if ( isset( $json[ 'version' ] ) ) {
            $this->version = $json[ 'version' ];
        }

        $this->name       = $json[ 'payable_rate_template_name' ];
        $this->breakdowns = $json[ 'breakdowns' ];

        return $this;
    }

    /**
     * @param $breakdowns
     *
     * @throws Exception
     */
    private function validateBreakdowns( $breakdowns ) {
        $size = mb_strlen( json_encode( $breakdowns, JSON_NUMERIC_CHECK ), '8bit' );

        if ( $size > self::MAX_BREAKDOWN_SIZE ) {
            throw new Exception( '`breakdowns` string is too large. Max size: 64kb', 400 );
        }

        if ( !isset( $breakdowns[ 'default' ] ) ) {
            throw new DomainException( '`default` node is MANDATORY in the breakdowns array.', 403 );
        }

        unset( $breakdowns[ 'default' ] );

        foreach ( $breakdowns as $language => $breakdown ) {
            $this->validateLanguage( $language );

            foreach ( $breakdown as $targetLanguage => $rates ) {
                $this->validateLanguage( $targetLanguage );
            }
        }
    }

    /**
     * @param $lang
     */
    private function validateLanguage( $lang ) {
        // rfc3066code --->  es-ES
        // isocode     --->  es
        $languages = Languages::getInstance();
        if ( !$languages->isValidLanguage( $lang ) ) {
            throw new DomainException( $lang . ' is not a supported language', 403 );
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function jsonSerialize() {
        return [
                'id'                         => (int)$this->id,
                'uid'                        => (int)$this->uid,
                'version'                    => (int)$this->version,
                'payable_rate_template_name' => $this->name,
                'breakdowns'                 => $this->getBreakdownsArray(),
                'createdAt'                  => DateTimeUtil::formatIsoDate( $this->created_at ),
                'modifiedAt'                 => DateTimeUtil::formatIsoDate( $this->modified_at ),
        ];
    }
}
