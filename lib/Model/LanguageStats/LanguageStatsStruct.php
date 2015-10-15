<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 22/09/15
 * Time: 16.42
 */
class LanguageStats_LanguageStatsStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct
{
    /**
     * @var string The date of this entry
     */
    public $date;

    /**
     * @var string Source language code in RFC3066 format
     */
    public $source;

    /**
     * @var string Target language code in RFC3066 format
     */
    public $target;

    /**
     * @var float The wordcount sum of all jobs having this language couple
     */
    public $total_wordcount;

    /**
     * @var float The postediting effort sum of all jobs having this language couple
     */
    public $total_postediting_effort;

    /**
     * @var int The time to edit sum of all jobs having this language couple
     */
    public $total_time_to_edit;

    /**
     * @var int The number of all jobs having this language couple
     */
    public $job_count;
}