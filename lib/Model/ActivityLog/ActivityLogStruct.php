<?php

namespace ActivityLog;

use DataAccess_AbstractDaoObjectStruct,
        DataAccess_IDaoStruct,
        DateTime;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 13/06/16
 * Time: 12:30
 */
class ActivityLogStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    /**
     * MAP to convert the values to the right string definition ( easy to put in another file or change localization )
     * @var array
     */
    protected $actionsStrings = array(
        self::DOWNLOAD_ANALYSIS_LOG => "Analysis log downloaded."
    );

    /**
     * MAP for Database values
     */
    const DOWNLOAD_ANALYSIS_LOG = 1;


    protected $cached_results = array();

    /**
     * @var int
     */
    public $id_project;

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $id_job;

    /**
     * @var int
     */
    public $action;

    /**
     * @var string
     */
    public $ip;

    /**
     * @var int
     */
    public $uid;

    /**
     * @var string
     */
    public $event_date;

    /**
     * @param $actionID int
     *
     * @return string
     */
    public function getAction( $actionID ){
        return $this->actionsStrings[ $actionID ];
    }

}