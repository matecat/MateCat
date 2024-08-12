<?php

namespace Filters;

use DataAccess_AbstractDaoSilentStruct;
use Date\DateTimeUtil;
use DomainException;
use Exception;
use Filters\DTO\Json;
use Filters\DTO\MSExcel;
use Filters\DTO\MSPowerpoint;
use Filters\DTO\MSWord;
use Filters\DTO\Xml;
use Filters\DTO\Yaml;
use JsonSerializable;
use stdClass;

class FiltersConfigTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements JsonSerializable {

    public ?int           $id            = null;
    public string         $name;
    public int            $uid;
    public ?string        $created_at    = null;
    public ?string        $modified_at   = null;
    public ?string        $deleted_at    = null;
    private ?Yaml         $yaml          = null;
    private ?Xml          $xml           = null;
    private ?Json         $json          = null;
    private ?MSWord       $ms_word       = null;
    private ?MSExcel      $ms_excel      = null;
    private ?MSPowerpoint $ms_powerpoint = null;

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

        $jsonDto  = new Json();
        $xmlDto   = new Xml();
        $yamlDto  = new Yaml();
        $excelDto = new MSExcel();
        $wordDto  = new MSWord();
        $pptDto   = new MSPowerpoint();

        // json
        if ( isset( $json[ 'json' ] ) ) {
            $rules = ( is_array( $json[ 'json' ] ) ) ? $json[ 'json' ] : json_decode( $json[ 'json' ], true );
            $jsonDto->fromArray( $rules );
        }

        // xml
        if ( isset( $json[ 'xml' ] ) ) {
            $xml = ( is_array( $json[ 'xml' ] ) ) ? $json[ 'xml' ] : json_decode( $json[ 'xml' ], true );
            $xmlDto->fromArray( $xml );
        }

        // yaml
        if ( isset( $json[ 'yaml' ] ) ) {
            $yaml = ( is_array( $json[ 'yaml' ] ) ) ? $json[ 'yaml' ] : json_decode( $json[ 'yaml' ], true );
            $yamlDto->fromArray( $yaml );
        }

        // ms excel
        if ( isset( $json[ 'ms_excel' ] ) ) {
            $excel = ( is_array( $json[ 'ms_excel' ] ) ) ? $json[ 'ms_excel' ] : json_decode( $json[ 'ms_excel' ], true );
            $excelDto->fromArray( $excel );
        }

        // ms word
        if ( isset( $json[ 'ms_word' ] ) ) {
            $word = ( is_array( $json[ 'ms_word' ] ) ) ? $json[ 'ms_word' ] : json_decode( $json[ 'ms_word' ], true );
            $wordDto->fromArray( $word );
        }

        // ms powerpoint
        if ( isset( $json[ 'ms_powerpoint' ] ) ) {
            $powerpoint = ( is_array( $json[ 'ms_powerpoint' ] ) ) ? $json[ 'ms_powerpoint' ] : json_decode( $json[ 'ms_powerpoint' ], true );
            $pptDto->fromArray( $powerpoint );
        }

        $this->setJson( $jsonDto );
        $this->setXml( $xmlDto );
        $this->setYaml( $yamlDto );
        $this->setMsExcel( $excelDto );
        $this->setMsWord( $wordDto );
        $this->setMsPowerpoint( $pptDto );

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
                'createdAt'     => DateTimeUtil::formatIsoDate( $this->created_at ),
                'modifiedAt'    => DateTimeUtil::formatIsoDate( $this->modified_at )
        ];
    }

    public static function default( int $uid ): stdClass {

        $default       = new stdClass();
        $default->id   = 0;
        $default->uid  = $uid;
        $default->name = "default";

        $default->xml           = Xml::default();
        $default->yaml          = Yaml::default();
        $default->json          = Json::default();
        $default->ms_word       = MSWord::default();
        $default->ms_excel      = MSExcel::default();
        $default->ms_powerpoint = MSPowerpoint::default();
        $default->created_at    = date( "Y-m-d H:i:s" );
        $default->modified_at   = date( "Y-m-d H:i:s" );

        return $default;

    }

}
