<?php

namespace ActivityLog;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 13/06/16
 * Time: 12:30
 */
class ActivityLogStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    /**
     * MAP to convert the values to the right string definition
     * ( easy to put in another file or change localization )
     * @var array
     */
    protected static $actionsStrings = [

        /* DOWNLOADS */
            self::DOWNLOAD_EDIT_LOG           => "Editing Log downloaded",
            self::DOWNLOAD_ANALYSIS_REPORT    => "Analysis Report downloaded",
            self::DOWNLOAD_PREVIEW            => "Preview downloaded",
            self::DOWNLOAD_GDRIVE_PREVIEW     => "Preview opened in Google Drive",
            self::DOWNLOAD_ORIGINAL           => "Original file downloaded",
            self::DOWNLOAD_TRANSLATION        => "Translation downloaded",
            self::DOWNLOAD_GDRIVE_TRANSLATION => "Translation opened in Google Drive",
            self::DOWNLOAD_JOB_TMX            => "Job TMX exported",
            self::DOWNLOAD_OMEGAT             => "OmegaT package exported",
            self::DOWNLOAD_XLIFF              => "XLIFF file(s) downloaded",
            self::DOWNLOAD_KEY_TMX            => "Private translation memory %s downloaded",

        /* ACCESSES */
            self::ACCESS_ANALYZE_PAGE         => "Access to the Analyze page",
            self::ACCESS_EDITLOG_PAGE         => "Access to the Editing Log page",
            self::ACCESS_TRANSLATE_PAGE       => "Access to the Translate page",
            self::ACCESS_REVISE_PAGE          => "Access to the Revise page",
            self::ACCESS_MANAGE_PAGE          => "Access to the Manage page",
            self::ACCESS_REVISE_SUMMARY_PAGE  => "Access to the Revise Summary page",

        /* OTHERS */
            self::PROJECT_CREATED             => "Project created.",
            self::JOB_UNARCHIVED              => "Job unarchived.",

            self::TRANSLATION_DELIVERED => 'Translation Delivered'

    ];

    /**
     * MAP for Database values
     */
    /* DOWNLOADS */
    const DOWNLOAD_EDIT_LOG           = 1;
    const DOWNLOAD_ANALYSIS_REPORT    = 2;
    const DOWNLOAD_PREVIEW            = 3;
    const DOWNLOAD_GDRIVE_PREVIEW     = 4;
    const DOWNLOAD_ORIGINAL           = 5;
    const DOWNLOAD_TRANSLATION        = 6;
    const DOWNLOAD_GDRIVE_TRANSLATION = 7;
    const DOWNLOAD_JOB_TMX            = 8;
    const DOWNLOAD_OMEGAT             = 9;
    const DOWNLOAD_XLIFF              = 10;
    const DOWNLOAD_KEY_TMX            = 11;

    /* ACCESSES */
    const ACCESS_ANALYZE_PAGE        = 12;
    const ACCESS_EDITLOG_PAGE        = 13;
    const ACCESS_TRANSLATE_PAGE      = 14;
    const ACCESS_REVISE_PAGE         = 15;
    const ACCESS_MANAGE_PAGE         = 16;
    const ACCESS_REVISE_SUMMARY_PAGE = 17;

    /* OTHERS */
    const PROJECT_CREATED = 18;
    const JOB_UNARCHIVED  = 19;

    const TRANSLATION_DELIVERED = 101;

    protected $cached_results = [];

    /**
     * @var int
     */
    public $id_project;

    /**
     * @var int
     */
    public $ID;

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
     * @var string
     */
    public $memory_key;

    /**
     * @var string
     */
    public $email;

    /**
     * @var string
     */
    public $first_name;

    /**
     * @var string
     */
    public $last_name;

    /**
     * @param $actionID int
     *
     * @return string
     */
    public static function getAction( $actionID ) {
        return self::$actionsStrings[ $actionID ];
    }

}