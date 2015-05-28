<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 18/05/15
 * Time: 15.24
 */
class DQF_DqfTaskStruct extends DQF_DqfAbstractStruct implements JsonSerializable {

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
     * The task ID
     * @var.int
     */
    public $task_id;

    /**
     * @var string
     */
    public $target_language;

    /**
     * @var string
     */
    public $file_name;


    /**
     * @return DQF_DqfTaskStruct
     */
    public static function getStruct() {
        return new DQF_DqfTaskStruct(
                array(
                        'v'               => Constants_DqfAPI::API_VERSION,
                        'app'             => 'MateCAT',
                        'app_version'     => INIT::$BUILD_NUMBER,
                        'type'            => 'task',
                        'api_key'         => null,
                        'project_id'      => null,
                        'task_id'         => null,
                        'target_language' => null,
                        'file_name'       => null,
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
    public function jsonSerialize() {
        return array(
                'v'           => $this->v,
                'app'         => $this->app,
                'app_version' => $this->app_version,
                'type'        => $this->type,
                'payload'     => array(
                        'api_key'         => $this->api_key,
                        'project_id'      => $this->project_id,
                        'task_id'         => $this->task_id,
                        'target_language' => $this->target_language,
                        'file_name'       => $this->file_name
                )
        );

    }


}