<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 05/10/15
 * Time: 11.33
 */
class EditLog_EditLogSegmentStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $source;

    /**
     * @var string
     */
    public $translation;

    /**
     * @var int
     */
    public $time_to_edit;

    /**
     * @var string
     */
    public $suggestion;

    /**
     * @var string
     */
    public $suggestions_array;

    /**
     * @var string
     */
    public $suggestion_source;

    /**
     * @var int
     */
    public $suggestion_match;

    /**
     * @var int
     */
    public $suggestion_position;

    /**
     * @var float
     */
    public $mt_qe;

    public $id_translator;

    /**
     * @var int
     */
    public $job_id;

    /**
     * @var string
     */
    public $job_source;

    /**
     * @var string
     */
    public $job_target;

    /**
     * @var int
     */
    public $raw_word_count;

    /**
     * @var string
     */
    public $proj_name;

    /**
     * @var float
     */
    public $secs_per_word;

    /**
     * @return float
     */
    public function getSecsPerWord(){
        return round( $this->time_to_edit / 1000 / $this->raw_word_count, 1 );
    }
}
