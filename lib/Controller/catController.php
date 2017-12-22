<?php
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use Exceptions\NotFoundError;


/**
 * Description of catController
 *
 * @property CatDecorator decorator
 * @author antonio
 */
class catController extends viewController {

    private $cid = "";
    private $jid = "";
    private $password = "";
    private $create_date = "";

    private $start_time = 0.00;

    private $job_stats = array();
    private $job_owner = "";

    private $job_not_found = false;
    private $job_archived = false;
    private $job_cancelled = false;

    private $first_job_segment = 0;
    private $firstSegmentOfFiles = '[]';
    private $fileCounter = '[]';

    private $qa_data = '[]';

    private $qa_overall = '';

    private $_keyList = array( 'totals' => array(), 'job_keys' => array() );

    public $target_code;
    public $source_code;

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk ;

    /**
     * @var Projects_ProjectStruct
     */
    public $project ;

    private $translation_engines;

    private $mt_id;

    /**
     * @var string
     * Review password generally corresponds to job password.
     * Translate and revise pages share the same password, exception
     * made for scenarios in which the review page must be protected
     * by second layer of authorization. In such cases, this variable
     * holds a different password than the job's password.
     */
    private $review_password = "";

    /**
     * @var WordCount_Struct
     */
    private $wStruct ;

    protected $templateName = "index.html";

    public function __construct() {
        $this->start_time = microtime( 1 ) * 1000;

        parent::__construct();

        parent::makeTemplate( $this->templateName );

        $filterArgs = array(
                'jid'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $getInput   = (object)filter_input_array( INPUT_GET, $filterArgs );

        $this->jid        = $getInput->jid;
        $this->password   = $getInput->password;

        $this->review_password = $getInput->password;

        $this->project = Projects_ProjectDao::findByJobId( $this->jid );

        /*
         * avoid Exception
         *
         * Argument 1 passed to FeatureSet::loadForProject() must be an instance of Projects_ProjectStruct, boolean given,
         */
        ( !$this->project ? $this->project = new Projects_ProjectStruct() : null ); // <-----

        $this->featureSet->loadForProject( $this->project ) ;

    }

    /**
     * findJobByIdAndPassword
     *
     * Finds the current chunk by job id and password. if in revision then
     * pass the control to a filter, to allow plugin to interact with the
     * authorization process.
     *
     * Filters may restore the password to the actual password contained in
     * `jobs` table, while the request may have come with a different password
     * for the purpose of access control.
     *
     * This is done to avoid the rewrite of preexisting implementations.
     */
    private function findJobByIdAndPassword() {
        if ( self::isRevision() ) {
            $this->password = $this->featureSet->filter(
                'filter_review_password_to_job_password',
                $this->password,
                $this->jid
            );

        }

        $this->chunk = Chunks_ChunkDao::getByIdAndPassword( $this->jid, $this->password );
    }

    public function doAction() {

        $this->featureSet->run('beginDoAction', $this);

        try {
            // TODO: why is this check here and not in constructor? At least it should be moved in a specific
            // function and not-found handled via exception.
            $this->findJobByIdAndPassword();
        } catch( NotFoundError $e ){
            $this->job_not_found = true;
            return;
        }

        $data = getSegmentsInfo( $this->jid, $this->password );

        //retrieve job owner. It will be useful also if the job is archived or cancelled
        $this->job_owner = ( $data[ 0 ][ 'job_owner' ] != "" ) ? $data[ 0 ][ 'job_owner' ] : "support@matecat.com";

        if ( $data[ 0 ][ 'status' ] == Constants_JobStatus::STATUS_CANCELLED ) {
            $this->job_cancelled = true;

            //stop execution
            return;
        }

        if ( $data[ 0 ][ 'status' ] == Constants_JobStatus::STATUS_ARCHIVED ) {
            $this->job_archived = true;
            //stop execution
            return;
        }

        /*
         * I prefer to use a programmatic approach to the check for the archive date instead of a pure query
         * because the query to check "Utils::getArchivableJobs($this->jid)" should be
         * executed every time a job is loaded ( F5 or CTRL+R on browser ) and it cost some milliseconds ( ~0.1s )
         * and it is little heavy for the database.
         * We use the data we already have from last query and perform
         * the check on the last translation only if the job is older than 30 days
         *
         */
        $lastUpdate  = new DateTime( $data[ 0 ][ 'last_update' ] );
        $oneMonthAgo = new DateTime();
        $oneMonthAgo->modify( '-' . INIT::JOB_ARCHIVABILITY_THRESHOLD . ' days' );

        if ( $lastUpdate < $oneMonthAgo && !$this->job_cancelled ) {

            $lastTranslationInJob = new Datetime( getLastTranslationDate( $this->jid ) );

            if ( $lastTranslationInJob < $oneMonthAgo ) {
                $res        = "job";
                $new_status = Constants_JobStatus::STATUS_ARCHIVED;
                //FIXME use Dao
                updateJobsStatus( $res, $this->jid, $new_status, $this->password );
                $this->job_archived = true;
            }

        }

        $this->pid = $data[0][ 'pid' ];
        $this->cid = $data[0][ 'cid' ];
        $this->source_code = $data[0][ 'source' ];
        $this->target_code = $data[0][ 'target' ];
        $this->create_date = $data[0][ 'create_date' ];
        if ( $data[0][ 'status' ] == Constants_JobStatus::STATUS_ARCHIVED ) {
            $this->job_archived = true;
            $this->job_owner    = $data[ 0 ][ 'job_owner' ];
        }
        $this->wStruct = CatUtils::getWStructFromJobArray( $data[0] );
        $this->job_stats = CatUtils::getFastStatsForJob( $this->wStruct );

        /**
         * get first segment of every file
         */
        $fileInfo     = getFirstSegmentOfFilesInJob( $this->jid );
        $TotalPayable = array();
        foreach ( $fileInfo as &$file ) {
            $file[ 'file_name' ] = ZipArchiveExtended::getFileName( $file[ 'file_name' ] );

            $TotalPayable[ $file[ 'id_file' ] ][ 'TOTAL_FORMATTED' ] = $file[ 'TOTAL_FORMATTED' ];
        }
        $this->firstSegmentOfFiles = json_encode( $fileInfo );
        $this->fileCounter         = json_encode( $TotalPayable );

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } elseif ( $this->logged_user->email == $data[ 0 ][ 'job_owner' ] ) {
            $this->userRole = TmKeyManagement_Filter::OWNER;
        } else {
            $this->userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
        }

        /*
         * Take the keys of the user
         */
        try {
            $_keyDao = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
            $dh      = new TmKeyManagement_MemoryKeyStruct( array( 'uid' => $this->logged_user->uid ) );
            $keyList = $_keyDao->read( $dh );

        } catch ( Exception $e ) {
            $keyList = array();
            Log::doLog( $e->getMessage() );
        }

        $reverse_lookup_user_personal_keys = array( 'pos' => array(), 'elements' => array() );
        /**
         * Set these keys as editable for the client
         *
         * @var $keyList TmKeyManagement_MemoryKeyStruct[]
         */
        foreach ( $keyList as $_j => $key ) {

            /**
             * @var $_client_tm_key TmKeyManagement_TmKeyStruct
             */

            //create a reverse lookup
            $reverse_lookup_user_personal_keys[ 'pos' ][ $_j ]      = $key->tm_key->key;
            $reverse_lookup_user_personal_keys[ 'elements' ][ $_j ] = $key;

            $this->_keyList[ 'totals' ][ $_j ] = new TmKeyManagement_ClientTmKeyStruct( $key->tm_key );

        }

        /*
         * Now take the JOB keys
         */
        $job_keyList = json_decode( $data[ 0 ][ 'tm_keys' ], true );

        /**
         * Start this N^2 cycle from keys of the job,
         * these should be statistically lesser than the keys of the user
         *
         * @var $keyList array
         */
        foreach ( $job_keyList as $jobKey ) {

            $jobKey = new TmKeyManagement_ClientTmKeyStruct( $jobKey );

            if ( $this->isLoggedIn() && count( $reverse_lookup_user_personal_keys[ 'pos' ] ) ) {

                /*
                 * If user has some personal keys, check for the job keys if they are present, and obfuscate
                 * when they are not
                 */
                $_index_position = array_search( $jobKey->key, $reverse_lookup_user_personal_keys[ 'pos' ] );
                if ( $_index_position !== false ) {

                    //I FOUND A KEY IN THE JOB THAT IS PRESENT IN MY KEYRING
                    //i'm owner?? and the key is an owner type key?
                    if ( !$jobKey->owner && $this->userRole != TmKeyManagement_Filter::OWNER ) {
                        $jobKey->r = $jobKey->{TmKeyManagement_Filter::$GRANTS_MAP[ $this->userRole ][ 'r' ]};
                        $jobKey->w = $jobKey->{TmKeyManagement_Filter::$GRANTS_MAP[ $this->userRole ][ 'w' ]};
                        $jobKey    = $jobKey->hideKey( $this->logged_user->uid );
                    } else {
                        if ( $jobKey->owner && $this->userRole != TmKeyManagement_Filter::OWNER ) {
                            // I'm not the job owner, but i know the key because it is in my keyring
                            // so, i can upload and download TMX, but i don't want it to be removed from job
                            // in tm.html relaxed the control to "key.edit" to enable buttons
                            // $jobKey = $jobKey->hideKey( $uid ); // enable editing

                        } else {
                            if ( $jobKey->owner && $this->userRole == TmKeyManagement_Filter::OWNER ) {
                                //do Nothing
                            }
                        }
                    }

                    //copy the is_shared value from the key inside the Keyring into the key coming from job
                    $jobKey->setShared( $reverse_lookup_user_personal_keys[ 'elements' ][ $_index_position ]->tm_key->isShared() );

                    unset( $this->_keyList[ 'totals' ][ $_index_position ] );

                } else {

                    /*
                     * This is not a key of that user, set right and obfuscate
                     */
                    $jobKey->r = true;
                    $jobKey->w = true;
                    $jobKey    = $jobKey->hideKey( -1 );

                }

                $this->_keyList[ 'job_keys' ][] = $jobKey;

            } else {
                /*
                 * This user is anonymous or it has no keys in its keyring, obfuscate all
                 */
                $jobKey->r                      = true;
                $jobKey->w                      = true;
                $this->_keyList[ 'job_keys' ][] = $jobKey->hideKey( -1 );

            }

        }

        //clean unordered keys
        $this->_keyList[ 'totals' ] = array_values( $this->_keyList[ 'totals' ] );


        /**
         * Retrieve information about job errors
         * ( Note: these information are fed by the revision process )
         * @see setRevisionController
         */

        $jobQA = new Revise_JobQA(
                $this->jid,
                $this->password,
                $this->wStruct->getTotal()
        );

        $jobQA->retrieveJobErrorTotals();

        $this->qa_data = json_encode( $jobQA->getQaData() );

        $jobVote = $jobQA->evalJobVote();
        $this->qa_overall = $jobVote[ 'minText' ];


        $engine = new EnginesModel_EngineDAO( Database::obtain() );

        //this gets all engines of the user
        if ( $this->isLoggedIn() ) {
            $engineQuery         = new EnginesModel_EngineStruct();
            $engineQuery->type   = 'MT';
            $engineQuery->uid    = $this->logged_user->uid;
            $engineQuery->active = 1;
            $mt_engines          = $engine->read( $engineQuery );
        } else {
            $mt_engines = array();
        }

        // this gets MyMemory
        $engineQuery         = new EnginesModel_EngineStruct();
        $engineQuery->type   = 'TM';
        $engineQuery->active = 1;
        $tms_engine          = $engine->setCacheTTL( 3600 * 24 * 30 )->read( $engineQuery );

        //this gets MT engine active for the job
        $engineQuery         = new EnginesModel_EngineStruct();
        $engineQuery->id     = $this->chunk->id_mt_engine ;
        $engineQuery->active = 1;
        $active_mt_engine    = $engine->setCacheTTL( 60 * 10 )->read( $engineQuery );

        /*
         * array_unique cast EnginesModel_EngineStruct to string
         *
         * EnginesModel_EngineStruct implements __toString method
         *
         */
        $this->translation_engines = array_unique( array_merge( $active_mt_engine, $tms_engine, $mt_engines ) );

        $this->_saveActivity();

    }

    protected function _saveActivity(){

        if( $this->isRevision() ){
            $action = ActivityLogStruct::ACCESS_REVISE_PAGE;
        } else {
            $action = ActivityLogStruct::ACCESS_TRANSLATE_PAGE;
        }

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->jid;
        $activity->id_project = $this->pid;
        $activity->action     = $action;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->logged_user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

    public function setTemplateVars() {

        if ( $this->job_not_found ) {
            parent::makeTemplate( 'job_not_found.html' );
            $this->template->support_mail = INIT::$SUPPORT_MAIL;
            header( "HTTP/1.0 404 Not Found" );
            return;
        }

        if( $this->job_cancelled ) parent::makeTemplate( 'job_cancelled.html' );
        if( $this->job_archived ) parent::makeTemplate( 'job_archived.html' );

        $this->template->jid         = $this->jid;
        $this->template->password    = $this->password;

        if( $this->job_cancelled || $this->job_archived ) {

            $this->template->pid                 = null;
            $this->template->source_code         = null;
            $this->template->target_code         = null;
            $this->template->firstSegmentOfFiles = 0;
            $this->template->fileCounter         = 0;

            $this->template->jobOwnerIsMe        = false;
            $this->template->support_mail        = INIT::$SUPPORT_MAIL;
            $this->template->owner_email         = INIT::$SUPPORT_MAIL;

            $team = $this->project->getTeam();
            if( !empty( $team ) ){
                $teamModel = new TeamModel( $team );
                $teamModel->updateMembersProjectsCount();
                $membersIdList = [];
                $ownerMail = null;
                if( $team->type == Constants_Teams::PERSONAL ){
                    $ownerMail = $team->getMembers()[0]->getUser()->getEmail();
                } else {

                    $ownerMail = ( new Users_UserDao() )->setCacheTTL( 60 * 60 * 24 )->getByUid( $this->project->id_assignee )->getEmail();
                    $membersIdList = array_map( function( $memberStruct ){
                        /**
                         * @var $memberStruct \Teams\MembershipStruct
                         */
                        return $memberStruct->uid;
                    }, $team->getMembers() );

                }
                $this->template->owner_email = $ownerMail;

                if( $this->logged_user->email == $ownerMail || in_array( $this->logged_user->uid, $membersIdList ) ){
                    $this->template->jobOwnerIsMe        = true;
                } else {
                    $this->template->jobOwnerIsMe        = false;
                }

            }

            $this->template->job_not_found       = $this->job_not_found;
            $this->template->job_archived        = ( $this->job_archived ) ? INIT::JOB_ARCHIVABILITY_THRESHOLD : '';
            $this->template->job_cancelled       = $this->job_cancelled;
            $this->template->logged_user         = ( $this->logged_user !== false ) ? $this->logged_user->shortName() : "";
            $this->template->extended_user       = ( $this->logged_user !== false ) ? trim( $this->logged_user->fullName() ) : "";

            return;

        } else {
            $this->template->pid                 = $this->pid;
            $this->template->source_code         = $this->source_code;
            $this->template->target_code         = $this->target_code;
            $this->template->firstSegmentOfFiles = $this->firstSegmentOfFiles;
            $this->template->fileCounter         = $this->fileCounter;
        }

        $this->template->owner_email        = $this->job_owner;
        $this->template->jobOwnerIsMe       = ( $this->logged_user->email == $this->job_owner );
        $this->template->get_public_matches = ( !$this->job->only_private_tm );
        $this->template->job_not_found      = $this->job_not_found;
        $this->template->job_archived       = ( $this->job_archived ) ? INIT::JOB_ARCHIVABILITY_THRESHOLD : '';
        $this->template->job_cancelled      = $this->job_cancelled;

        $this->template->page        = 'cattool';
        $this->template->cid         = $this->cid;
        $this->template->create_date = $this->create_date;
        $this->template->pname       = $this->project->name;

        $this->template->mt_engines = $this->translation_engines;
        $this->template->mt_id      = $this->chunk->id_mt_engine ;

        $this->template->first_job_segment   = $this->chunk->job_first_segment ;
        $this->template->last_job_segment    = $this->chunk->job_last_segment ;

        $this->template->owner_email         = $this->job_owner;



        $this->job_stats[ 'STATUS_BAR_NO_DISPLAY' ] = ( $this->project->status_analysis == Constants_ProjectStatus::STATUS_DONE ? '' : 'display:none;' );
        $this->job_stats[ 'ANALYSIS_COMPLETE' ]     = ( $this->project->status_analysis == Constants_ProjectStatus::STATUS_DONE ? true : false );

        $this->template->user_keys             = $this->_keyList;
        $this->template->job_stats             = $this->job_stats;
        $this->template->stat_quality          = $this->qa_data;

        $this->template->overall_quality_class = strtolower( $this->getQaOverall() );

        $end_time                    = microtime( true ) * 1000;
        $load_time                   = $end_time - $this->start_time;
        $this->template->load_time   = $load_time;
        $this->template->tms_enabled = var_export( (bool) $this->chunk->id_tms , true );
        $this->template->mt_enabled  = var_export( (bool) $this->chunk->id_mt_engine , true );

        $this->template->warningPollingInterval = 1000 * ( INIT::$WARNING_POLLING_INTERVAL );
        $this->template->segmentQACheckInterval = 1000 * ( INIT::$SEGMENT_QA_CHECK_INTERVAL );

        $this->template->maxFileSize    = INIT::$MAX_UPLOAD_FILE_SIZE;
        $this->template->maxTMXFileSize = INIT::$MAX_UPLOAD_TMX_FILE_SIZE;

        $this->template->hideMatchesClass = ( self::isRevision() ? '' : ' hideMatches' );

        $this->template->tagLockCustomizable  = ( INIT::$UNLOCKABLE_TAGS == true ) ? true : false;
        //FIXME: temporarily disabled
        $this->template->editLogClass         = ""; //$this->getEditLogClass();
        $this->template->maxNumSegments       = INIT::$MAX_NUM_SEGMENTS;
        $this->template->copySourceInterval   = INIT::$COPY_SOURCE_INTERVAL;
        $this->template->time_to_edit_enabled = INIT::$TIME_TO_EDIT_ENABLED;

        /*
         * Line Feed PlaceHolding System
         */
        $this->template->brPlaceholdEnabled = $placeHoldingEnabled = true;

        if ( $placeHoldingEnabled ) {

            $this->template->lfPlaceholder        = CatUtils::lfPlaceholder;
            $this->template->crPlaceholder        = CatUtils::crPlaceholder;
            $this->template->crlfPlaceholder      = CatUtils::crlfPlaceholder;
            $this->template->lfPlaceholderClass   = CatUtils::lfPlaceholderClass;
            $this->template->crPlaceholderClass   = CatUtils::crPlaceholderClass;
            $this->template->crlfPlaceholderClass = CatUtils::crlfPlaceholderClass;
            $this->template->lfPlaceholderRegex   = CatUtils::lfPlaceholderRegex;
            $this->template->crPlaceholderRegex   = CatUtils::crPlaceholderRegex;
            $this->template->crlfPlaceholderRegex = CatUtils::crlfPlaceholderRegex;

            $this->template->tabPlaceholder      = CatUtils::tabPlaceholder;
            $this->template->tabPlaceholderClass = CatUtils::tabPlaceholderClass;
            $this->template->tabPlaceholderRegex = CatUtils::tabPlaceholderRegex;

            $this->template->nbspPlaceholder      = CatUtils::nbspPlaceholder;
            $this->template->nbspPlaceholderClass = CatUtils::nbspPlaceholderClass;
            $this->template->nbspPlaceholderRegex = CatUtils::nbspPlaceholderRegex;
        }

        if ( INIT::$COMMENTS_ENABLED ) {
            $this->template->comments_enabled = true;
            $this->template->sse_base_url     = INIT::$SSE_BASE_URL;
        }

        $this->template->isGDriveProject =  $this->isCurrentProjectGDrive();

        $this->template->uses_matecat_filters = Utils::isJobBasedOnMateCatFilters($this->jid);

        //Maybe some plugin want disable the Split from the config
        $this->template->splitSegmentEnabled = var_export(true, true);

        $this->decorator = new CatDecorator( $this, $this->template );
        $this->decorator->decorate();

        $this->featureSet->appendDecorators(
            'CatDecorator',
            $this,
            $this->template
        );
    }

    public function getJobStats() {
      return $this->job_stats ;
    }

    /**
     * @return Chunks_ChunkStruct
     */
    public function getChunk() {
      return $this->chunk ;
    }

    /**
     * @return string
     */

    public function getReviewPassword() {
        return $this->review_password ;
    }


    public function getQaOverall() {
        // TODO: is this str_replace really required?
        return str_replace( ' ', '', $this->qa_overall );
    }

    /**
     * @return string
     */
    private function getEditLogClass() {
        $return = "";

        $editLogModel = new EditLog_EditLogModel( $this->jid, $this->password );
        $issue = $editLogModel->getMaxIssueLevel();

        $dao = new EditLog_EditLogDao(Database::obtain());

        if( !$dao->isEditLogEmpty($this->jid, $this->password)) {
            if ( $issue > 0 ) {
                $return = "edit_" . $issue;
            }
        }

        return $return;
    }

    public function isCurrentProjectGDrive() {
        return \Projects_ProjectDao::isGDriveProject($this->chunk->id_project);
    }

}
