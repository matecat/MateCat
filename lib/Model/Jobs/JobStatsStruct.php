<?php

namespace Jobs;


/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 31/03/16
 * Time: 15.43
 *
 */
class JobStatsStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $draft;

    /**
     * @var int
     */
    public $translated;

    /**
     * @var int
     */
    public $approved;

    /**
     * @var int
     */
    public $rejected;

    /**
     * @var int
     */
    public $total;

    /**
     * @var int
     */
    public $progress;

    /**
     * @var string
     */
    public $total_formatted;

    /**
     * @var string
     */
    public $progress_formatted;

    /**
     * @var string
     */
    public $approved_formatted;

    /**
     * @var string
     */
    public $rejected_formatted;

    /**
     * @var string
     */
    public $draft_formatted;

    /**
     * @var string
     */
    public $translated_formatted;

    /**
     * @var int
     */
    public $approved_perc;

    /**
     * @var int
     */
    public $rejected_perc;

    /**
     * @var int
     */
    public $draft_perc;

    /**
     * @var int
     */
    public $translated_perc;

    /**
     * @var int
     */
    public $progress_perc;

    /**
     * @var int
     */
    public $translated_perc_formatted;

    /**
     * @var int
     */
    public $draft_perc_formatted;

    /**
     * @var int
     */
    public $approved_perc_formatted;

    /**
     * @var int
     */
    public $rejected_perc_formatted;

    /**
     * @var int
     */
    public $progress_perc_formatted;

    /**
     * @var string
     */
    public $todo_formatted;

    /**
     * @var string
     */
    public $download_status;

    /**
     * @var string
     */
    public $status_bar_no_display;

    /**
     * @var bool
     */
    public $analysis_complete;

    /**
     * @var array
     */
    protected $job_stats;

    /**
     * JobStatsStruct constructor.
     *
     * Override parent method to make lowercase the key values
     *
     * @param array $array_params
     */
    public function __construct( Array $array_params = array() ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->{strtolower($property)} = $value;
            }
        }
        $this->tryValidator();
    }

    public function isAllTranslated(){
        return $this->translated_perc == '100';
    }

    public function isAllApproved(){
        return $this->approved_perc == '100';
    }

    public function isDownloadable() {
        return (
                $this->todo_formatted == 0 &&
                $this->analysis_complete
        );
    }

    public function isCompleted(){
        return $this->todo_formatted == '0';
    }

}