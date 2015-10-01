<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/10/15
 * Time: 11.58
 */
class EditLog_EditLogModel
{
    const MAX_SEGMENTS_PER_PAGE = 50;

    private static $segments_per_page = 10;
    private static $pageNumber = 1;

    private $jid = "";
    private $password = "";
    private $project_status = "";

    private $job_archived = false;
    private $job_owner_email;
    private $jobData;
    private $job_stats;
    private $stats;
    private $data;
    private $languageStatsData;
    private $db;

    public function __construct()
    {
        $this->db = Database::obtain();
    }

    public function controllerDoAction()
    {
        //pay a little query to avoid to fetch 5000 rows
        $this->jobData = $jobData = getJobData($this->jid, $this->password);

        $wStruct = new WordCount_Struct();
        $wStruct->setIdJob($this->jid);
        $wStruct->setJobPassword($this->password);
        $wStruct->setNewWords($jobData['new_words']);
        $wStruct->setDraftWords($jobData['draft_words']);
        $wStruct->setTranslatedWords($jobData['translated_words']);
        $wStruct->setApprovedWords($jobData['approved_words']);
        $wStruct->setRejectedWords($jobData['rejected_words']);

        if ($jobData['status'] == Constants_JobStatus::STATUS_ARCHIVED || $jobData['status'] == Constants_JobStatus::STATUS_CANCELLED) {
            //this job has been archived
            $this->job_archived = true;
            $this->job_owner_email = $jobData['job_owner'];
        }

        $tmp = CatUtils::getEditingLogData($this->jid, $this->password);

        $this->data = $tmp[0];
        $this->stats = $tmp[1];

        $this->job_stats = CatUtils::getFastStatsForJob($wStruct);

        $proj = getProject($jobData['id_project']);
        $this->project_status = $proj[0];

        $__langStatsDao = new LanguageStats_LanguageStatsDAO(Database::obtain());
        $maxDate = $__langStatsDao->getLastDate();

        $languageSearchObj = new LanguageStats_LanguageStatsStruct();
        $languageSearchObj->date = $maxDate;
        $languageSearchObj->source = $this->data[0]['source_lang'];
        $languageSearchObj->target = $this->data[0]['target_lang'];

        $this->languageStatsData = $__langStatsDao->read($languageSearchObj);
        $this->languageStatsData = $this->languageStatsData[0];
    }

    //TODO: change this horrible name
    public function doAction()
    {

        //fetch variables

        //get data from DB
        //TODO: pagination included

        //process data

        //do sorting

        //return output
    }

    /**
     * @param int $segments_per_page
     */
    public static function setSegmentsPerPage($segments_per_page)
    {
        self::$segments_per_page = $segments_per_page;
    }

    /**
     * @param int $pageNumber
     */
    public static function setPageNumber($pageNumber)
    {
        self::$pageNumber = $pageNumber;
    }

    /**
     * @param string $jid
     */
    public function setJid($jid)
    {
        $this->jid = $jid;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return mixed
     */
    public function getJobStats()
    {
        return $this->job_stats;
    }

    /**
     * @return mixed
     */
    public function getStats()
    {
        return $this->stats;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return mixed
     */
    public function getLanguageStatsData()
    {
        return $this->languageStatsData;
    }


}