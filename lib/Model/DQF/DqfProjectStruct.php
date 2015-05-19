<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 18/05/15
 * Time: 15.24
 */
class DQF_DqfProjectStruct extends DQF_DqfAbstractStruct implements JsonSerializable {

    //all the following parameters will be wrapped into a 'payload' parameter during json_encode

    /**
     * The API key
     * @var string
     */
    public $api_key;

    /**
     * The project ID
     * @var int
     */
    public $project_id;

    /**
     * The project name
     * @var string
     */
    public $name;

    /**
     * @var int
     */
    public $quality_level;

    /**
     * @var string
     */
    public $process;

    /**
     * @var string
     */
    public $source_language;

    /**
     * @var string
     */
    public $contentType;

    /**
     * @var string
     */
    public $industry;

    public static function getStruct() {
        return new DQF_DqfProjectStruct(
                array(
                        'v'               => Constants_DqfAPI::API_VERSION,
                        'app'             => 'MateCAT',
                        'app_version'     => INIT::$BUILD_NUMBER,
                        'type'            => 'project',
                        'api_key'         => null,
                        'project_id'      => null,
                        'name'            => null,
                        'quality_level'   => 2,
                        'process'         => 3,
                        'source_language' => null,
                        'contentType'     => 9,
                        'industry'        => 19,

                )
        );
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    function jsonSerialize() {
        return array(
                'v'           => $this->v,
                'app'         => $this->app,
                'app_version' => $this->app_version,
                'type'        => $this->type,
                'payload'     => array(
                        'api_key'         => $this->api_key,
                        'project_id'      => $this->project_id,
                        'name'            => $this->name,
                        'quality_level'   => $this->quality_level,
                        'process'         => $this->process,
                        'source_language' => $this->source_language,
                        'contentType'     => $this->contentType,
                        'industry'        => $this->industry,
                )
        );

    }


}