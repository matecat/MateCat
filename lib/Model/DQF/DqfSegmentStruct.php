<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 19/05/15
 * Time: 10.50
 */
class DQF_DqfSegmentStruct extends DQF_DqfAbstractStruct implements JsonSerializable {

    /**
     * The task ID
     * @var int
     */
    public $task_id;

    /**
     * @var int
     */
    public $segment_id;

    /**
     * @var string
     */
    public $source_segment;

    /**
     * The suggestion from the MT or TM
     * @var string
     */
    public $target_segment;

    /**
     * The translator's translation
     * @var string
     */
    public $new_target_segment;

    /**
     * @var int
     */
    public $time;

    /**
     * @var float|String
     */
    public $tm_match;

    /**
     * @var int
     */
    public $cattool;

    /**
     * MT engine's ID
     * @var int
     */
    public $mtengine;

    /**
     * MT engine's version
     * @var string
     */
    public $mt_engine_version;

    /**
     * @return DQF_DqfSegmentStruct
     */
    public static function getStruct() {
        return new DQF_DqfSegmentStruct(
                array(
                        'v'                  => Constants_DqfAPI::API_VERSION,
                        'app'                => 'MateCAT',
                        'app_version'        => INIT::$BUILD_NUMBER,
                        'type'               => 'task',
                        'task_id'            => null,
                        'segment_id'         => null,
                        'source_segment'     => null,
                        'target_segment'     => null,
                        'new_target_segment' => null,
                        'time'               => null,//TODO: time() ?
                        'tm_match'           => null,
                        'cattool'            => 1,
                        'mtengine'           => null,
                        'mt_engine_version'  => null

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
                        'task_id'            => $this->task_id,
                        'segment_id'         => $this->segment_id,
                        'source_segment'     => $this->source_segment,
                        'target_segment'     => $this->target_segment,
                        'new_target_segment' => $this->new_target_segment,
                        'time'               => $this->time,
                        'tm_match'           => $this->tm_match,
                        'cattool'            => $this->cattool,
                        'mtengine'           => $this->mtengine,
                        'mt_engine_version'  => $this->mt_engine_version
                )
        );

    }

}