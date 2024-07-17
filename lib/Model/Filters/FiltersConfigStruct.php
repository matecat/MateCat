<?php

namespace Filters;

use DataAccess_AbstractDaoSilentStruct;
use Date\DateTimeUtil;
use DomainException;
use Filters\DTO\Json;
use Filters\DTO\MSExcel;
use Filters\DTO\MSPowerpoint;
use Filters\DTO\MSWord;
use Filters\DTO\Xml;
use Filters\DTO\Yaml;
use JsonSerializable;

class FiltersConfigStruct extends DataAccess_AbstractDaoSilentStruct implements JsonSerializable
{
    public $id;
    public $name;
    public $uid;
    public $created_at;
    public $modified_at;
    public $deleted_at;
    private $yaml = null;
    private $xml = null;
    private $json = null;
    private $ms_word = null;
    private $ms_excel = null;
    private $ms_powerpoint = null;

    /**
     * @param Yaml|null $yaml
     */
    public function setYaml(?Yaml $yaml): void
    {
        $this->yaml = $yaml;
    }

    /**
     * @param Xml|null $xml
     */
    public function setXml(?Xml $xml): void
    {
        $this->xml = $xml;
    }

    /**
     * @param Json|null $json
     */
    public function setJson(?Json $json): void
    {
        $this->json = $json;
    }

    /**
     * @param MSWord|null $ms_word
     */
    public function setMsWord(?MSWord $ms_word): void
    {
        $this->ms_word = $ms_word;
    }

    /**
     * @param MSExcel|null $ms_excel
     */
    public function setMsExcel(?MSExcel $ms_excel): void
    {
        $this->ms_excel = $ms_excel;
    }

    /**
     * @param MSPowerpoint|null $ms_powerpoint
     */
    public function setMsPowerpoint(?MSPowerpoint $ms_powerpoint): void
    {
        $this->ms_powerpoint = $ms_powerpoint;
    }

    /**
     * @param $json
     * @param null $uid
     * @return $this
     */
    public function hydrateFromJSON( $json, $uid = null )
    {
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
            $jsonDto->fromArray( $json[ 'json' ] );
        }

        // xml
        if ( isset( $json[ 'xml' ] ) ) {
            $xmlDto->fromArray( $json[ 'xml' ] );
        }

        // yaml
        if ( isset( $json[ 'yaml' ] ) ) {
            $yamlDto->fromArray( $json[ 'yaml' ] );
        }

        // ms excel
        if ( isset( $json[ 'ms_excel' ] ) ) {
            $excelDto->fromArray( $json[ 'ms_excel' ] );
        }

        // ms word
        if ( isset( $json[ 'ms_word' ] ) ) {
            $wordDto->fromArray( $json[ 'ms_word' ] );
        }

        // ms powerpoint
        if ( isset( $json[ 'ms_powerpoint' ] ) ) {
            $pptDto->fromArray( $json[ 'ms_powerpoint' ] );
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
     * @return false|string
     * @throws \Exception
     */
    public function getRulesAsString()
    {
        return json_encode([
            'xml'           => $this->xml,
            'yaml'          => $this->yaml,
            'json'          => $this->json,
            'ms_word'       => $this->ms_word,
            'ms_excel'      => $this->ms_excel,
            'ms_powerpoint' => $this->ms_powerpoint,
        ]);
    }

    /**
     * @return array|mixed
     * @throws \Exception
     */
    public function jsonSerialize()
    {
        return [
            'id'            => (int)$this->id,
            'uid'           => (int)$this->uid,
            'name'          => $this->name,
            'xml'           => $this->xml,
            'yaml'          => $this->yaml,
            'json'          => $this->json,
            'ms_word'       => $this->ms_word,
            'ms_excel'      => $this->ms_excel,
            'ms_powerpoint' => $this->ms_powerpoint,
            'createdAt'     => DateTimeUtil::formatIsoDate( $this->created_at ),
            'modifiedAt'    => DateTimeUtil::formatIsoDate( $this->modified_at ),
            'deletedAt'     => DateTimeUtil::formatIsoDate( $this->deleted_at ),
        ];
    }
}
