<?php

namespace FiltersXliffConfig;

use Date\DateTimeUtil;
use DomainException;
use Exception;
use FiltersXliffConfig\Filters\DTO\Json;
use FiltersXliffConfig\Filters\DTO\MSExcel;
use FiltersXliffConfig\Filters\DTO\MSPowerpoint;
use FiltersXliffConfig\Filters\DTO\MSWord;
use FiltersXliffConfig\Filters\DTO\Xml;
use FiltersXliffConfig\Filters\DTO\Yaml;
use FiltersXliffConfig\Filters\FiltersConfigModel;
use FiltersXliffConfig\Xliff\DTO\Xliff12Rule;
use FiltersXliffConfig\Xliff\DTO\Xliff20Rule;
use FiltersXliffConfig\Xliff\DTO\XliffConfigModel;
use JsonSerializable;
use stdClass;

class FiltersXliffConfigTemplateStruct implements JsonSerializable {
    public $id;
    public $name;
    public $uid;
    public $created_at;
    public $modified_at;
    public $deleted_at;

    /**
     * @var XliffConfigModel
     */
    private $xliff = null;

    /**
     * @var FiltersConfigModel
     */
    private $filters = null;

    /**
     * @param XliffConfigModel $xliff
     */
    public function setXliff( XliffConfigModel $xliff ) {
        $this->xliff = $xliff;
    }

    /**
     * @return XliffConfigModel
     */
    public function getXliff() {
        return $this->xliff;
    }

    /**
     * @return FiltersConfigModel
     */
    public function getFilters() {
        return $this->filters;
    }

    /**
     * @param FiltersConfigModel $filters
     */
    public function setFilters( FiltersConfigModel $filters ) {
        $this->filters = $filters;
    }

    /**
     * @param string $json
     *
     * @return $this
     *
     * @throws Exception
     */
    public function hydrateFromJSON( $json, $uid = null ) {
        $json = json_decode( $json, true );

        if (
                !isset( $json[ 'name' ] ) or
                !isset( $json[ 'filters' ] ) or
                !isset( $json[ 'xliff' ] )
        ) {
            throw new DomainException( "Cannot instantiate a new FiltersXliffConfigTemplateStruct. Invalid data provided.", 400 );
        }

        if ( empty( $uid ) && empty( $json[ 'uid' ] ) ) {
            throw new DomainException( "Cannot instantiate a new FiltersXliffConfigTemplateStruct. Invalid user id provided.", 400 );
        }

        $this->uid  = $json[ 'uid' ] ?? $uid;
        $this->name = $json[ 'name' ];
        $xliff      = ( !is_array( $json[ 'xliff' ] ) ) ? json_decode( $json[ 'xliff' ], true ) : $json[ 'xliff' ];
        $filters    = ( !is_array( $json[ 'filters' ] ) ) ? json_decode( $json[ 'filters' ], true ) : $json[ 'filters' ];

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

        $filtersConfig = new FiltersConfigModel();
        $xliffConfig   = new XliffConfigModel();

        // xliff
        if ( !empty( $xliff ) ) {

            // xliff12
            if ( isset( $xliff[ 'xliff12' ] ) and is_array( $xliff[ 'xliff12' ] ) ) {
                foreach ( $xliff[ 'xliff12' ] as $xliff12Rule ) {
                    $rule = new Xliff12Rule( $xliff12Rule[ 'states' ], $xliff12Rule[ 'analysis' ], $xliff12Rule[ 'editor' ], $xliff12Rule[ 'match_category' ] ?? null );
                    $xliffConfig->addRule( $rule );
                }
            }

            // xliff20
            if ( isset( $xliff[ 'xliff20' ] ) and is_array( $xliff[ 'xliff20' ] ) ) {
                foreach ( $xliff[ 'xliff20' ] as $xliff20Rule ) {
                    $rule = new Xliff20Rule( $xliff20Rule[ 'states' ], $xliff20Rule[ 'analysis' ], $xliff20Rule[ 'editor' ], $xliff20Rule[ 'match_category' ] ?? null );
                    $xliffConfig->addRule( $rule );
                }
            }
        }

        // filters
        $jsonDto  = new Json();
        $xmlDto   = new Xml();
        $yamlDto  = new Yaml();
        $excelDto = new MSExcel();
        $wordDto  = new MSWord();
        $pptDto   = new MSPowerpoint();

        if ( !empty( $filters ) ) {

            // json
            if ( isset( $filters[ 'json' ] ) ) {
                $jsonDto->fromArray( $filters[ 'json' ] );
            }

            // xml
            if ( isset( $filters[ 'xml' ] ) ) {
                $xmlDto->fromArray( $filters[ 'xml' ] );
            }

            // yaml
            if ( isset( $filters[ 'yaml' ] ) ) {
                $yamlDto->fromArray( $filters[ 'yaml' ] );
            }

            // ms excel
            if ( isset( $filters[ 'ms_excel' ] ) ) {
                $excelDto->fromArray( $filters[ 'ms_excel' ] );
            }

            // ms word
            if ( isset( $filters[ 'ms_word' ] ) ) {
                $wordDto->fromArray( $filters[ 'ms_word' ] );
            }

            // ms powerpoint
            if ( isset( $filters[ 'ms_powerpoint' ] ) ) {
                $pptDto->fromArray( $filters[ 'ms_powerpoint' ] );
            }
        }

        $filtersConfig->setJson( $jsonDto );
        $filtersConfig->setXml( $xmlDto );
        $filtersConfig->setYaml( $yamlDto );
        $filtersConfig->setMsExcel( $excelDto );
        $filtersConfig->setMsWord( $wordDto );
        $filtersConfig->setMsPowerpoint( $pptDto );

        $this->setFilters( $filtersConfig );
        $this->setXliff( $xliffConfig );

        return $this;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function jsonSerialize() {
        return [
                'id'         => (int)$this->id,
                'uid'        => (int)$this->uid,
                'name'       => $this->name,
                'filters'    => ( $this->filters !== null ) ? $this->filters : new stdClass(),
                'xliff'      => ( $this->xliff !== null ) ? $this->xliff : new stdClass(),
                'createdAt'  => DateTimeUtil::formatIsoDate( $this->created_at ),
                'modifiedAt' => DateTimeUtil::formatIsoDate( $this->modified_at ),
                'deletedAt'  => DateTimeUtil::formatIsoDate( $this->deleted_at ),
        ];
    }
}