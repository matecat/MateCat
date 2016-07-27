<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/10/15
 * Time: 11.58
 */
class EditLog_EditLogModel {
    //number of percentage point under which the post editing effor evaluation is still accepted;
    const PEE_THRESHOLD = 1;
    const MAX_SEGMENTS_PER_PAGE = 50;
    const CACHETIME = 108000;
    const EDIT_TIME_SLOW_CUT = 30;
    const EDIT_TIME_FAST_CUT = 0.25;

    private static $segments_per_page = 10;
    private static $start_id = -1;
    private static $sort_by = "sid";

    private $jid = "";
    private $password = "";
    private $project_status = "";
    private $job_archived = false;

    private $job_owner_email;
    private $jobData;
    private $job_stats;
    private $jobEmpty = false;
    private $stats;
    private $data;
    private $pagination;
    private $languageStatsData;
    private $db;

    public function __construct( $jid, $password ) {
        $this->db       = Database::obtain();
        $this->jid      = $jid;
        $this->password = $password;
    }

    public function controllerDoAction() {
        //pay a little query to avoid to fetch 5000 rows
        $this->jobData   = getJobData( $this->jid, $this->password );
        $this->job_stats = $this->getFastStatsForJob();

        if ( $this->jobData[ 'status' ] == Constants_JobStatus::STATUS_ARCHIVED || $this->jobData == Constants_JobStatus::STATUS_CANCELLED ) {
            //this job has been archived
            $this->job_archived    = true;
            $this->job_owner_email = $this->jobData[ 'job_owner' ];
        }

        if ( self::$start_id == -1 ) {
            self::$start_id = $this->jobData[ 'job_first_segment' ];
        }

        //TODO: portare dentro il codice
        $proj = getProject( $this->jobData[ 'id_project' ] );

        try {

            $tmp = $this->getEditLogData();

            $this->data       = $tmp[ 0 ];
            $this->pagination = $tmp[ 2 ];

            foreach ( $this->data as $i => $dataRow ) {
                /**
                 * @var $dataRow EditLog_EditLogSegmentClientStruct
                 */
                $this->data[ $i ] = $dataRow->toArray();
            }

            $this->stats = $tmp[ 1 ];

            $this->project_status = $proj[ 0 ];

            $__langStatsDao = new LanguageStats_LanguageStatsDAO( Database::obtain() );
            $maxDate        = $__langStatsDao->getLastDate();

            $languageSearchObj         = new LanguageStats_LanguageStatsStruct();
            $languageSearchObj->date   = $maxDate;
            $languageSearchObj->source = $this->data[ 0 ][ 'job_source' ];
            $languageSearchObj->target = $this->data[ 0 ][ 'job_target' ];

            $this->languageStatsData = $__langStatsDao->read( $languageSearchObj );
            $this->languageStatsData = $this->languageStatsData[ 0 ];
        } catch ( Exception $exn ) {
            if ( $exn->getCode() == -1 ) {
                $this->jobEmpty = true;
                //set JobData anyway to fill the template for the goBack button
                $this->data[] = array(
                        'proj_name'  => $proj[ 0 ][ 'name' ],
                        'job_source' => $this->jobData[ 'source' ],
                        'job_target' => $this->jobData[ 'target' ]
                );
            }
        }
    }

    //TODO: change this horrible name
    public function doAction() {

        //fetch variables

        //get data from DB
        //TODO: pagination included

        //process data

        //do sorting

        //return output
    }

    /**
     * @return array
     */
    private function getFastStatsForJob() {
        $wStruct = new WordCount_Struct();
        $wStruct->setIdJob( $this->jid );
        $wStruct->setJobPassword( $this->password );
        $wStruct->setNewWords( $this->jobData[ 'new_words' ] );
        $wStruct->setDraftWords( $this->jobData[ 'draft_words' ] );
        $wStruct->setTranslatedWords( $this->jobData[ 'translated_words' ] );
        $wStruct->setApprovedWords( $this->jobData[ 'approved_words' ] );
        $wStruct->setRejectedWords( $this->jobData[ 'rejected_words' ] );

        return CatUtils::getFastStatsForJob( $wStruct );
    }

    private function getEditLogData( $use_ter_diff = false ) {
        $editLogDao = new EditLog_EditLogDao( Database::obtain() );
        $data       = $editLogDao->getSegments( $this->getJid(), $this->getPassword(), self::$start_id );

        //get translation mismatches and convert the array in a hashmap
        $translationMismatchList = $editLogDao->getTranslationMismatches( $this->getJid() );
        foreach ( $translationMismatchList as $idx => $translMismRow ) {
            $translMismRow[ $translMismRow[ 'segment_hash' ] ] = (bool)$translMismRow[ 'translation_mismatch' ];
        }

        $__pagination_prev = PHP_INT_MAX;
        $__pagination_next = -2147483648;     //PHP_INT_MIN

        $stat_too_slow = array();
        $stat_too_fast = array();

        if ( !$data ) {
            throw new Exception( 'There are no changes in this job', -1 );
        }

        $stats[ 'total-word-count' ] = 0;
        $stat_mt                     = array();
        $stat_valid_rwc              = array();
        $stat_rwc                    = array();
        $stat_valid_tte              = array();
        $stat_pee                    = array();
        $stat_ter                    = array();

        $output_data = array();
        foreach ( $data as $seg ) {

            //if the segment is before the current one
            if ( $seg->id < self::$start_id ) {
                if ( $seg->id <= $__pagination_prev ) {
                    $__pagination_prev = $seg->id;
                }
                continue;
            }

            if ( $seg->id > $__pagination_next ) {
                $__pagination_next = $seg->id;
            }

            $displaySeg = new EditLog_EditLogSegmentClientStruct(
                    $seg->toArray()
            );

            $displaySeg->suggestion_match .= "%";
            $displaySeg->job_id               = $this->jid;
            $tte                              = CatUtils::parse_time_to_edit( $displaySeg->time_to_edit );
            $displaySeg->display_time_to_edit = "$tte[1]m:$tte[2]s";

            $stat_rwc[] = $seg->raw_word_count;

            // by definition we cannot have a 0 word sentence. It is probably a - or a tag, so we want to consider at least a word.
            if ( $seg->raw_word_count < 1 ) {
                $displaySeg->raw_word_count = 1;
            }
            $displaySeg->raw_word_count = round($displaySeg->raw_word_count);
            //todo: remove this
            $displaySeg->secs_per_word = $seg->getSecsPerWord();

            if ( ( $displaySeg->secs_per_word < self::EDIT_TIME_SLOW_CUT ) &&
                    ( $displaySeg->secs_per_word > self::EDIT_TIME_FAST_CUT )
            ) {
                $displaySeg->stats_valid = true;

                $stat_valid_rwc[] = $seg->raw_word_count;
                $stat_spw[]       = $displaySeg->secs_per_word;
            } else {
                $displaySeg->stats_valid = false;
            }

            // Stats
            if ( $displaySeg->secs_per_word >= self::EDIT_TIME_SLOW_CUT ) {
                $stat_too_slow[] = $seg->raw_word_count;
            }
            if ( $displaySeg->secs_per_word <= self::EDIT_TIME_FAST_CUT ) {
                $stat_too_fast[] = $seg->raw_word_count;
            }

            $displaySeg->secs_per_word .= "s";

            $displaySeg->pe_effort_perc = $displaySeg->getPEE();

            if ( $displaySeg->pe_effort_perc < 0 ) {
                $displaySeg->pe_effort_perc = 0;
            }
            if ( $displaySeg->pe_effort_perc > 100 ) {
                $displaySeg->pe_effort_perc = 100;
            }

            $stat_pee[] = $displaySeg->pe_effort_perc * $seg->raw_word_count;

            $displaySeg->pe_effort_perc .= "%";

            $lh   = Langs_Languages::getInstance();
            $lang = $lh->getIsoCode( $lh->getLocalizedName( $seg->job_target ) );

            $sug_for_diff = CatUtils::placehold_xliff_tags( $seg->suggestion );
            $tra_for_diff = CatUtils::placehold_xliff_tags( $seg->translation );

            //with this patch we have warnings when accessing indexes
            if ( $use_ter_diff ) {
                $ter = MyMemory::diff_tercpp( $sug_for_diff, $tra_for_diff, $lang );
            } else {
                $ter = array();
            }

            $displaySeg->ter = @$ter[ 1 ] * 100;
            $stat_ter[]      = $displaySeg->ter * $seg->raw_word_count;
            $displaySeg->ter = round( @$ter[ 1 ] * 100 ) . "%";
            $diff_ter        = @$ter[ 0 ];

            if ( $seg->suggestion <> $seg->translation ) {

                //force use of third party ter diff
                if ( $use_ter_diff ) {
                    $displaySeg->diff = $diff_ter;
                } else {
                    $diff_PE = MyMemory::diff_html( $sug_for_diff, $tra_for_diff );
                    // we will use diff_PE until ter_diff will not work properly
                    $displaySeg->diff = $diff_PE;
                }

                //$seg[ 'diff_ter' ] = $diff_ter;

            } else {
                $displaySeg->diff = '';
            }

            $displaySeg->diff = CatUtils::restore_xliff_tags_for_view( $displaySeg->diff );

            // BUG: While suggestions source is not correctly set
            if ( ( $displaySeg->suggestion_match == "85%" ) || ( $displaySeg->suggestion_match == "86%" ) ) {
                $displaySeg->suggestion_source = 'Machine Translation';
                $stat_mt[]                     = $seg->raw_word_count;
            } else {
                $displaySeg->suggestion_source = 'TM';
            }


            $array_patterns = array(
                    rtrim( CatUtils::lfPlaceholderRegex, 'g' ),
                    rtrim( CatUtils::crPlaceholderRegex, 'g' ),
                    rtrim( CatUtils::crlfPlaceholderRegex, 'g' ),
                    rtrim( CatUtils::tabPlaceholderRegex, 'g' ),
                    rtrim( CatUtils::nbspPlaceholderRegex, 'g' ),
            );

            $array_replacements_csv      = array(
                    '\n',
                    '\r',
                    '\r\n',
                    '\t',
                    Utils::unicode2chr( 0Xa0 ),
            );
            $displaySeg->source_csv      = preg_replace( $array_patterns, $array_replacements_csv, $seg->source );
            $displaySeg->translation_csv = preg_replace( $array_patterns, $array_replacements_csv, $seg->translation );
            $displaySeg->sug_csv         = preg_replace( $array_patterns, $array_replacements_csv, $displaySeg->suggestion_view );
            $displaySeg->diff_csv        = preg_replace( $array_patterns, $array_replacements_csv, $displaySeg->diff );


            $array_replacements          = array(
                    '<span class="_0A"></span><br />',
                    '<span class="_0D"></span><br />',
                    '<span class="_0D0A"></span><br />',
                    '<span class="_tab">&#9;</span>',
                    '<span class="_nbsp">&nbsp;</span>',
            );
            $displaySeg->source          = preg_replace( $array_patterns, $array_replacements, $seg->source );
            $displaySeg->translation     = preg_replace( $array_patterns, $array_replacements, $seg->translation );
            $displaySeg->suggestion_view = preg_replace( $array_patterns, $array_replacements, $displaySeg->suggestion_view );
            $displaySeg->diff            = preg_replace( $array_patterns, $array_replacements, $displaySeg->diff );

            $displaySeg->source          = trim( CatUtils::rawxliff2view( $seg->source ) );
            $displaySeg->suggestion_view = trim( CatUtils::rawxliff2view( $seg->suggestion ) );
            $displaySeg->translation     = trim( CatUtils::rawxliff2view( $seg->translation ) );


            if ( $seg->mt_qe == 0 ) {
                $displaySeg->mt_qe = 'N/A';
            }

            $displaySeg->num_translation_mismatch = @(int)$translationMismatchList[ $displaySeg->segment_hash ];
            $displaySeg->evaluateWarningString();

            $output_data[] = $displaySeg;
        }

        $pagination  = $this->evaluatePagination( $__pagination_prev, $__pagination_next + 1 );
        $globalStats = $this->evaluateGlobalStats();

        $stats[ 'valid-word-count' ] = round($globalStats[ 'raw_words' ]);

        //TODO: this will not work anymore
        $stats[ 'edited-word-count' ] = array_sum( $stat_rwc );
        if ( $stats[ 'edited-word-count' ] > 0 ) {
            $stats[ 'too-slow-words' ] = round( array_sum( $stat_too_slow ) / $stats[ 'edited-word-count' ], 2 ) * 100;
            $stats[ 'too-fast-words' ] = round( array_sum( $stat_too_fast ) / $stats[ 'edited-word-count' ], 2 ) * 100;
            $stats[ 'avg-pee' ]        = round( array_sum( $stat_pee ) / array_sum( $stat_rwc ) ) . "%";
            $stats[ 'avg-ter' ]        = round( array_sum( $stat_ter ) / array_sum( $stat_rwc ) ) . "%";
        }

        $stats[ 'mt-words' ]        = round( array_sum( $stat_mt ) / $stats[ 'edited-word-count' ], 2 ) * 100;
        $stats[ 'tm-words' ]        = 100 - $stats[ 'mt-words' ];
        $stats[ 'total-valid-tte' ] = round( $globalStats[ 'tot_tte' ] );

        // Non weighted...
        // $stats['avg-secs-per-word'] = round(array_sum($stat_spw)/count($stat_spw),1);
        // Weighted
        $stats[ 'avg-secs-per-word' ] = round( $globalStats[ 'secs_per_word' ] / 1000, 1 );
        $stats[ 'est-words-per-day' ] = number_format( round( 3600 * 8 / $stats[ 'avg-secs-per-word' ] ), 0, '.', ',' );

        // Last minute formatting (after calculations)
        $temp                       = CatUtils::parse_time_to_edit( round( $stats[ 'total-valid-tte' ] ) );
        $stats[ 'total-valid-tte' ] = "$temp[0]h:$temp[1]m:$temp[2]s";

        $stats[ 'total-tte-seconds' ] = $temp[ 0 ] * 3600 + $temp[ 1 ] * 60 + $temp[ 2 ];
        $stats[ 'avg-pee' ]           = round( $globalStats[ 'avg_pee' ], 2 );
        $stats[ 'avg-pee' ] .= "%";

        return array( $output_data, $stats, $pagination );

    }

    private function evaluatePagination( $prev_id, $next_id ) {
        $editLogDao = new EditLog_EditLogDao( Database::obtain() );
        $editLogDao->setCacheTTL( self::CACHETIME );
        $pagination = array(
                'first'        => PHP_INT_MAX,
                'prev'         => $prev_id,
                'current'      => self::$start_id,
                'next'         => $next_id,
                'last'         => -2147483648,  //PHP_INT_MIN
                'page_index'   => null,
                'current_page' => 1,
                'last_page'    => -1
        );

        $pagination[ 'last' ]  = $editLogDao->getLastPage_firstID( $this->getJid(), $this->getPassword() );
        $pagination[ 'first' ] = $editLogDao->getFirstPage_firstID( $this->getJid(), $this->getPassword() );

        $pagination[ 'page_index' ] = $editLogDao->getPagination( $this->getJid(), $this->getPassword() );

        $pagination[ 'current_page' ] = self::evaluateCurrentPage( $pagination[ 'page_index' ], self::$start_id );
        $pagination[ 'last_page' ]    = count( $pagination[ 'page_index' ] );

        //fix next page id if necessary
        if ( $pagination[ 'next' ] > $pagination[ 'last' ] ) {
            $pagination[ 'next' ] = $pagination[ 'last' ];
        }

        if ( $pagination[ 'prev' ] < $pagination[ 'first' ] ) {
            $pagination[ 'prev' ] = $pagination[ 'first' ];
        } else if ( $pagination[ 'prev' ] == PHP_INT_MAX ) {
            $pagination[ 'prev' ] = $pagination[ 'first' ];
        }

        if ( $pagination[ 'prev' ] == $pagination[ 'first' ] ) {
            unset( $pagination[ 'first' ] );
        }

        if ( $pagination[ 'next' ] == $pagination[ 'last' ] ) {
            unset( $pagination[ 'last' ] );
        }

        //this happens because the first time that the page is loaded
        //the start segment could not be visible ( show_in_cattool = 0 )
        if ( $pagination[ 'current' ] < $pagination[ 'prev' ] ) {
            $pagination[ 'current' ] = $pagination[ 'prev' ];
        }

        if ( $pagination[ 'next' ] <= $pagination[ 'current' ] ) {
            unset( $pagination[ 'next' ] );
        }

        if ( $pagination[ 'prev' ] == $pagination[ 'current' ] ) {
            unset( $pagination[ 'prev' ] );
        }

        //there is only one page: pagination is useless
        if( $pagination['current_page'] == $pagination['last_page']){
            $pagination = array();
        }

        return $pagination;
    }

    private static function evaluateCurrentPage( Array $index, $startIndex ) {
        $i = 0;
        while ( $i < count( $index ) && $startIndex > $index[ $i ][ 'start_segment' ] ) {
            $i++;
        }

        return $index[ $i ][ 'page' ];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function evaluateGlobalStats() {
        $dao = new EditLog_EditLogDao( Database::obtain() );

        return $dao->getGlobalStats( $this->jid, $this->password );
    }

    /**
     * @return float
     */
    public function evaluateOverallTTE() {
        $this->loadLanguageStats();

        return round(
                $this->languageStatsData->total_time_to_edit / ( 1000 * $this->languageStatsData->total_word_count ),
                2
        );
    }

    /**
     * @return float
     */
    public function evaluateOverallPEE() {
        $this->loadLanguageStats();

        return round(
                $this->languageStatsData->total_post_editing_effort / ( $this->languageStatsData->job_count ),
                2
        );
    }

    /**
     * @throws Exception Throws Exception on empty query parameters
     */
    private function loadLanguageStats() {
        if ( empty( $this->jobData ) ) {
            $this->jobData = getJobData( $this->jid, $this->password );
        }

        if ( empty( $this->languageStatsData ) ) {
            $__langStatsDao = new LanguageStats_LanguageStatsDAO( Database::obtain() );
            $maxDate        = $__langStatsDao->getLastDate();

            $languageSearchObj       = new LanguageStats_LanguageStatsStruct();
            $languageSearchObj->date = $maxDate;

            $languageSearchObj->source = $this->jobData[ 'source' ];
            $languageSearchObj->target = $this->jobData[ 'target' ];

            $this->languageStatsData = $__langStatsDao->read( $languageSearchObj );
            $this->languageStatsData = $this->languageStatsData[ 0 ];
        }
    }

    /**
     * @return bool
     */
    public function isPEEslow() {
        return ( str_replace( "%", "", $this->stats[ 'avg-pee' ] ) ) + self::PEE_THRESHOLD < $this->evaluateOverallPEE();
    }

    /**
     * @return bool
     */
    public function isTTEfast() {

        return $this->stats[ 'avg-secs-per-word' ] < $this->evaluateOverallTTE();
    }

    /**
     * @return int
     */
    public function getMaxIssueLevel() {
        $globalStats                        = $this->evaluateGlobalStats();
        $this->stats[ 'avg-pee' ]           = round( $globalStats[ 'avg_pee' ], 2 );
        $this->stats[ 'avg-secs-per-word' ] = round( $globalStats[ 'secs_per_word' ] / 1000, 1 );

        $returnIssue = Constants_EditLogIssue::OK;

        if ( $this->isPEEslow() ) {
            $returnIssue = Constants_EditLogIssue::ERROR;
        }

        return $returnIssue;
    }

    // GETTERS AND SETTERS
    /**
     * @param int $segments_per_page
     */
    public static function setSegmentsPerPage( $segments_per_page ) {
        self::$segments_per_page = $segments_per_page;
    }

    /**
     * @param int $start_id
     */
    public static function setStartId( $start_id ) {
        self::$start_id = $start_id;
    }

    /**
     * @param string $sort_by
     */
    public static function setSortBy( $sort_by ) {
        self::$sort_by = $sort_by;
    }

    /**
     * @return string
     */
    public function getJid() {
        return $this->jid;
    }

    /**
     * @return string
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getProjectStatus() {
        return $this->project_status;
    }

    /**
     * @return boolean
     */
    public function isJobArchived() {
        return $this->job_archived;
    }

    /**
     * @return mixed
     */
    public function getJobOwnerEmail() {
        return $this->job_owner_email;
    }

    /**
     * @return mixed
     */
    public function getJobData() {
        return $this->jobData;
    }

    /**
     * @return mixed
     */
    public function getJobStats() {
        return $this->job_stats;
    }

    /**
     * @return mixed
     */
    public function getStats() {
        return $this->stats;
    }

    /**
     * @return mixed
     */
    public function getData() {
        return $this->data;
    }

    /**
     * @return mixed
     */
    public function getLanguageStatsData() {
        return $this->languageStatsData;
    }

    /**
     * @return mixed
     */
    public function getPagination() {
        return $this->pagination;
    }

    /**
     * @return boolean
     */
    public function isJobEmpty() {
        return $this->jobEmpty;
    }

    public function getProjectId(){
        return $this->jobData[ 'id_project' ];
    }

}