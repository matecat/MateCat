<?php

namespace Filters;

use \DataAccess\AbstractDaoSilentStruct;
use Date\DateTimeUtil;
use DomainException;
use Exception;
use Filters\DTO\Dita;
use Filters\DTO\Json;
use Filters\DTO\MSExcel;
use Filters\DTO\MSPowerpoint;
use Filters\DTO\MSWord;
use Filters\DTO\Xml;
use Filters\DTO\Yaml;
use JsonSerializable;

class FiltersConfigTemplateStruct extends \DataAccess\AbstractDaoSilentStruct implements JsonSerializable {

    public ?int          $id            = null;
    public string        $name;
    public int           $uid;
    public ?string       $created_at    = null;
    public ?string       $modified_at   = null;
    public ?string       $deleted_at    = null;
    public ?Yaml         $yaml          = null;
    public ?Xml          $xml           = null;
    public ?Json         $json          = null;
    public ?MSWord       $ms_word       = null;
    public ?MSExcel      $ms_excel      = null;
    public ?MSPowerpoint $ms_powerpoint = null;
    public ?Dita         $dita          = null;

    /**
     * @return null
     */
    public function getYaml(): ?Yaml {
        return $this->yaml;
    }

    /**
     * @return null
     */
    public function getXml(): ?Xml {
        return $this->xml;
    }

    /**
     * @return null
     */
    public function getJson(): ?Json {
        return $this->json;
    }

    /**
     * @return null
     */
    public function getMsWord(): ?MSWord {
        return $this->ms_word;
    }

    /**
     * @return null
     */
    public function getMsExcel(): ?MSExcel {
        return $this->ms_excel;
    }

    /**
     * @return null
     */
    public function getMsPowerpoint(): ?MSPowerpoint {
        return $this->ms_powerpoint;
    }

    /**
     * @return null
     */
    public function getDita(): ?Dita {
        return $this->dita;
    }

    /**
     * @param Yaml|null $yaml
     */
    public function setYaml( ?Yaml $yaml ): void {
        $this->yaml = $yaml;
    }

    /**
     * @param Xml|null $xml
     */
    public function setXml( ?Xml $xml ): void {
        $this->xml = $xml;
    }

    /**
     * @param Json|null $json
     */
    public function setJson( ?Json $json ): void {
        $this->json = $json;
    }

    /**
     * @param MSWord|null $ms_word
     */
    public function setMsWord( ?MSWord $ms_word ): void {
        $this->ms_word = $ms_word;
    }

    /**
     * @param MSExcel|null $ms_excel
     */
    public function setMsExcel( ?MSExcel $ms_excel ): void {
        $this->ms_excel = $ms_excel;
    }

    /**
     * @param MSPowerpoint|null $ms_powerpoint
     */
    public function setMsPowerpoint( ?MSPowerpoint $ms_powerpoint ): void {
        $this->ms_powerpoint = $ms_powerpoint;
    }

    /**
     * @param Dita|null $dita
     */
    public function setDita( ?Dita $dita ): void {
        $this->dita = $dita;
    }

    protected function hydrateDtoFromArray( string $dtoClass, array $data ) {

        $dto = new $dtoClass();
        $dto->fromArray( $data );

        switch ( $dtoClass ) {
            case Json::class:
                $this->setJson( $dto );
                break;
            case Xml::class:
                $this->setXml( $dto );
                break;
            case Yaml::class:
                $this->setYaml( $dto );
                break;
            case MSExcel::class:
                $this->setMsExcel( $dto );
                break;
            case MSWord::class:
                $this->setMsWord( $dto );
                break;
            case MSPowerpoint::class:
                $this->setMsPowerpoint( $dto );
                break;
            case Dita::class:
                $this->setDita( $dto );
                break;
        }

    }

    /**
     * @param array $json
     *
     * @return void
     */
    public function hydrateAllDto( array $json ) {

        if ( isset( $json[ 'json' ] ) ) {
            is_array( $json[ 'json' ] ) ? $this->hydrateDtoFromArray( Json::class, $json[ 'json' ] ) : $this->hydrateDtoFromArray( Json::class, json_decode( $json[ 'json' ], true ) );
        }

        // xml
        if ( isset( $json[ 'xml' ] ) ) {
            is_array( $json[ 'xml' ] ) ? $this->hydrateDtoFromArray( Xml::class, $json[ 'xml' ] ) : $this->hydrateDtoFromArray( Xml::class, json_decode( $json[ 'xml' ], true ) );
        }

        // yaml
        if ( isset( $json[ 'yaml' ] ) ) {
            is_array( $json[ 'yaml' ] ) ? $this->hydrateDtoFromArray( Yaml::class, $json[ 'yaml' ] ) : $this->hydrateDtoFromArray( Yaml::class, json_decode( $json[ 'yaml' ], true ) );
        }

        // ms excel
        if ( isset( $json[ 'ms_excel' ] ) ) {
            is_array( $json[ 'ms_excel' ] ) ? $this->hydrateDtoFromArray( MSExcel::class, $json[ 'ms_excel' ] ) : $this->hydrateDtoFromArray( MSExcel::class, json_decode( $json[ 'ms_excel' ], true ) );
        }

        // ms word
        if ( isset( $json[ 'ms_word' ] ) ) {
            is_array( $json[ 'ms_word' ] ) ? $this->hydrateDtoFromArray( MSWord::class, $json[ 'ms_word' ] ) : $this->hydrateDtoFromArray( MSWord::class, json_decode( $json[ 'ms_word' ], true ) );
        }

        // ms powerpoint
        if ( isset( $json[ 'ms_powerpoint' ] ) ) {
            is_array( $json[ 'ms_powerpoint' ] ) ? $this->hydrateDtoFromArray( MSPowerpoint::class, $json[ 'ms_powerpoint' ] ) : $this->hydrateDtoFromArray( MSPowerpoint::class, json_decode( $json[ 'ms_powerpoint' ], true ) );
        }

        // dita
        if ( isset( $json[ 'dita' ] ) ) {
            is_array( $json[ 'dita' ] ) ? $this->hydrateDtoFromArray( Dita::class, $json[ 'dita' ] ) : $this->hydrateDtoFromArray( Dita::class, json_decode( $json[ 'dita' ], true ) );
        }

    }

    /**
     * @param      $json
     * @param null $uid
     *
     * @return $this
     */
    public function hydrateFromJSON( $json, $uid = null ): FiltersConfigTemplateStruct {
        $json = json_decode( $json, true );

        if ( !isset( $json[ 'name' ] ) ) {
            throw new DomainException( "Cannot instantiate a new FiltersConfigStruct. Invalid data provided.", 400 );
        }

        if ( empty( $uid ) && empty( $json[ 'uid' ] ) ) {
            throw new DomainException( "Cannot instantiate a new FiltersConfigStruct. Invalid user id provided.", 400 );
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

        // set defaults
        $this->setJson( new Json() );
        $this->setXml( new Xml() );
        $this->setYaml( new Yaml() );
        $this->setMsExcel( new MSExcel() );
        $this->setMsWord( new MSWord() );
        $this->setMsPowerpoint( new MSPowerpoint() );
        $this->setDita( new Dita() );

        $this->hydrateAllDto( $json );

        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function jsonSerialize(): array {
        return [
                'id'            => $this->id,
                'uid'           => $this->uid,
                'name'          => $this->name,
                'xml'           => $this->xml,
                'yaml'          => $this->yaml,
                'json'          => $this->json,
                'ms_word'       => $this->ms_word,
                'ms_excel'      => $this->ms_excel,
                'ms_powerpoint' => $this->ms_powerpoint,
                'dita'          => $this->dita,
                'created_at'    => DateTimeUtil::formatIsoDate( $this->created_at ),
                'modified_at'   => DateTimeUtil::formatIsoDate( $this->modified_at )
        ];
    }

}
