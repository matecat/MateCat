<?php

namespace Jobs;
use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

/**
 * Created by PhpStorm.
 * User: lavoro
 * Date: 24/06/16
 * Time: 17:26
 */

class PeeJobStatsStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct{

    /**
     * @var int
     */
    public $id_job;

    /**
     * @var string
     */
    public $password;

    /**
     * @var string
     */
    public $fuzzy_band;

    /**
     * @var string
     */
    public $source;

    /**
     * @var string
     */
    public $target;

    /**
     * @var float
     */
    public $avg_post_editing_effort;

    /**
     * @var int
     */
    public $total_time_to_edit;

    /**
     * @var int
     */
    public $total_raw_wc;

    /**
     * @var int
     */
    public $job_count;
}