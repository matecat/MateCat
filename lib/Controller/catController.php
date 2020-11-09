<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use Exceptions\AuthorizationError;
use Exceptions\NotFoundException;
use LQA\ChunkReviewStruct;
use TmKeyManagement\UserKeysModel;
use Engines_Intento as Intento;

/**
 * Description of catController
 *
 * @property CatDecorator decorator
 * @author antonio
 */
class catController extends viewController {

    protected $received_password;

    private   $cid             = "";
    protected $jid             = "";
    protected $password        = "";
    protected $review_password = "";
    private   $create_date     = "";

    private $start_time = 0.00;

    private $job_stats = [];
    private $job_owner = "";

    private $job_not_found = false;
    private $job_archived  = false;
    private $job_cancelled = false;

    private $qa_data = '[]';

    private $qa_overall = '';

    public $target_code;
    public $source_code;
    private $revision ;

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
                ),
                'revision' => array( 'filter' => FILTER_VALIDATE_INT ),
        );

        $getInput   = (object)filter_input_array( INPUT_GET, $filterArgs );

        $this->jid               = $getInput->jid;
        $this->received_password = $getInput->password;
        $this->revision          = $getInput->revision;

        $this->project = Projects_ProjectDao::findByJobId( $this->jid );

        /*
         * avoid Exception
         *
         * Argument 1 passed to loadForProject() must be an instance of Projects_ProjectStruct, boolean given,
         */
        ( !$this->project ? $this->project = new Projects_ProjectStruct() : null ); // <-----

        $this->featureSet->loadForProject( $this->project ) ;
    }

    /**
     * @return mixed|void
     * @throws \Exception
     */
    public function doAction() {
        $this->featureSet->run('beginDoAction', $this);

        try {
            $this->findJobByIdPasswordAndSourcePage();
            $this->featureSet->run('handleProjectType', $this);
        } catch( NotFoundException $e ){
            $this->job_not_found = true;
            return;
        }

        //retrieve job owner. It will be useful also if the job is archived or cancelled
        $this->job_owner = ( $this->chunk->owner != "" ) ? $this->chunk->owner : INIT::$MAILER_RETURN_PATH;

        if ( $this->chunk->status_owner == Constants_JobStatus::STATUS_CANCELLED ) {
            $this->job_cancelled = true;
            return;
        }

        if ( $this->chunk->status_owner == Constants_JobStatus::STATUS_ARCHIVED ) {
            $this->job_archived = true;
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
        $lastUpdate  = new DateTime( $this->chunk->last_update );
        $oneMonthAgo = new DateTime();
        $oneMonthAgo->modify( '-' . INIT::JOB_ARCHIVABILITY_THRESHOLD . ' days' );

        if ( $lastUpdate < $oneMonthAgo && !$this->job_cancelled ) {

            $lastTranslationInJob = new Datetime( ( new Translations_SegmentTranslationDao )->lastTranslationByJobOrChunk( $this->jid )->translation_date );

            if ( $lastTranslationInJob < $oneMonthAgo ) {
                Jobs_JobDao::updateJobStatus( $this->chunk, Constants_JobStatus::STATUS_ARCHIVED );
                $this->job_archived = true;
            }

        }

        $this->pid = $this->project->id;
        $this->cid = $this->project->id_customer;
        $this->source_code = $this->chunk->source;
        $this->target_code = $this->chunk->target;
        $this->create_date = $this->chunk->create_date;


        $this->wStruct = CatUtils::getWStructFromJobArray( $this->chunk, $this->project );
        $this->job_stats = CatUtils::getFastStatsForJob( $this->wStruct );

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } elseif ( $this->user->email == $this->chunk->status_owner ) {
            $this->userRole = TmKeyManagement_Filter::OWNER;
        } else {
            $this->userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
        }

        $userKeys = new UserKeysModel($this->user, $this->userRole ) ;
        $this->template->user_keys = $userKeys->getKeys( $this->chunk->tm_keys ) ;

        /**
         * Retrieve information about job errors
         * ( Note: these information are fed by the revision process )
         * @see setRevisionController
         */

        $reviseClass = new Constants_Revise;

        $jobQA = new Revise_JobQA(
                $this->jid,
                $this->password,
                $this->wStruct->getTotal(),
                $reviseClass
        );

        list( $jobQA, $reviseClass ) = $this->featureSet->filter( "overrideReviseJobQA", [ $jobQA, $reviseClass ], $this->jid, $this->password, $this->wStruct->getTotal() );


        $jobQA->retrieveJobErrorTotals();

        $this->qa_data = json_encode( $jobQA->getQaData() );

        $jobVote = $jobQA->evalJobVote();
        $this->qa_overall = $jobVote[ 'minText' ];


        $engine = new EnginesModel_EngineDAO( Database::obtain() );

        //this gets all engines of the user
        if ( $this->isLoggedIn() ) {
            $engineQuery         = new EnginesModel_EngineStruct();
            $engineQuery->type   = 'MT';
            $engineQuery->uid    = $this->user->uid;
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

    /**
     * findJobByIdPasswordAndSourcePage
     *
     * Finds the current chunk by job id, password and source page. if in revision then
     * pass the control to a filter, to allow plugin to interact with the
     * authorization process.
     *
     * Filters may restore the password to the actual password contained in
     * `jobs` table, while the request may have come with a different password
     * for the purpose of access control.
     *
     * This is done to avoid the rewrite of preexisting implementations.
     *
     * @throws \Exception
     */
    private function findJobByIdPasswordAndSourcePage() {
        if ( self::isRevision() ) {
            /** @var ChunkReviewStruct $chunkReviewStruct */
            $chunkReviewStruct = $this->featureSet->filter(
                    'filter_review_password_to_job_password',
                    new ChunkReviewStruct( [
                            'password'        => $this->received_password,
                            'review_password' => $this->received_password,
                            'id_job'          => $this->jid
                    ] ),
                    Utils::getSourcePage()
            );
            $this->chunk = $chunkReviewStruct->getChunk();
            $this->password = $chunkReviewStruct->password;
            $this->review_password = $chunkReviewStruct->review_password;
        } else {
            $this->password = $this->received_password;
            $this->review_password = $this->password;
            $this->chunk = Chunks_ChunkDao::getByIdAndPassword( $this->jid, $this->password );
        }
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
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

    /**
     * @return mixed|void
     * @throws NotFoundException
     * @throws AuthorizationError
     */
    public function setTemplateVars() {

        if ( $this->job_not_found ) {
            parent::makeTemplate( 'job_not_found.html' );
            $this->template->support_mail = INIT::$SUPPORT_MAIL;
            throw new NotFoundException( "Job Not Found." );
        }

        if( $this->job_cancelled ) parent::makeTemplate( 'job_cancelled.html' );
        if( $this->job_archived ) parent::makeTemplate( 'job_archived.html' );

        $this->template->jid = $this->jid;

        $this->template->id_team = null;

        if( $this->job_cancelled || $this->job_archived ) {

            $this->template->pid                 = null;
            $this->template->source_code         = null;
            $this->template->target_code         = null;

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
                    $assignee = ( new Users_UserDao() )->setCacheTTL( 60 * 60 * 24 )->getByUid( $this->project->id_assignee );
                    if ($assignee) {
                        $ownerMail = $assignee->getEmail();
                    } else {
                        $ownerMail = INIT::$SUPPORT_MAIL;
                    }
                    $membersIdList = array_map( function( $memberStruct ){
                        /**
                         * @var $memberStruct \Teams\MembershipStruct
                         */
                        return $memberStruct->uid;
                    }, $team->getMembers() );

                }
                $this->template->owner_email = $ownerMail;

                if( $this->user->email == $ownerMail || in_array( $this->user->uid, $membersIdList ) ){
                    $this->template->jobOwnerIsMe        = true;
                } else {
                    $this->template->jobOwnerIsMe        = false;
                }

            }

            $this->template->job_not_found       = $this->job_not_found;
            $this->template->job_archived        = ( $this->job_archived ) ? INIT::JOB_ARCHIVABILITY_THRESHOLD : '';
            $this->template->job_cancelled       = $this->job_cancelled;
            $this->template->logged_user         = ( $this->isLoggedIn() !== false ) ? $this->user->shortName() : "";
            $this->template->extended_user       = ( $this->isLoggedIn() !== false ) ? trim( $this->user->fullName() ) : "";
            $this->template->password            = $this->password;

            throw new AuthorizationError( "Forbidden, Job archived/cancelled." );

        } else {
            $this->template->pid                 = $this->pid;
            $this->template->source_code         = $this->source_code;
            $this->template->target_code         = $this->target_code;
        }

        if ( !empty( $this->project->id_team ) ) {
            $this->template->id_team = $this->project->id_team;
        }

        $this->template->owner_email        = $this->job_owner;
        $this->template->jobOwnerIsMe       = ( $this->user->email == $this->job_owner );
        $this->template->get_public_matches = ( !$this->chunk->only_private_tm );
        $this->template->job_not_found      = $this->job_not_found;
        $this->template->job_archived       = ( $this->job_archived ) ? INIT::JOB_ARCHIVABILITY_THRESHOLD : '';
        $this->template->job_cancelled      = $this->job_cancelled;

        $this->template->page        = 'cattool';
        $this->template->cid         = $this->cid;
        $this->template->create_date = $this->create_date;
        $this->template->pname       = $this->project->name;

        $this->template->mt_engines = $this->translation_engines;
        $this->template->mt_id      = $this->chunk->id_mt_engine ;

        $this->template->translation_engines_intento_providers = Intento::getProviderList();

        $this->template->owner_email         = $this->job_owner;

        $this->job_stats[ 'STATUS_BAR_NO_DISPLAY' ] = ( $this->project->status_analysis == Constants_ProjectStatus::STATUS_DONE ? '' : 'display:none;' );
        $this->job_stats[ 'ANALYSIS_COMPLETE' ]     = ( $this->project->status_analysis == Constants_ProjectStatus::STATUS_DONE ? true : false );

        $this->template->job_stats             = $this->job_stats;
        $this->template->stat_quality          = $this->qa_data;

        $this->template->overall_quality_class = strtolower( $this->getQaOverall() );

        $end_time                    = microtime( true ) * 1000;
        $load_time                   = $end_time - $this->start_time;
        $this->template->load_time   = $load_time;

        $this->template->first_job_segment   = $this->chunk->job_first_segment;
        $this->template->tms_enabled = var_export( (bool) $this->chunk->id_tms , true );
        $this->template->mt_enabled  = var_export( (bool) $this->chunk->id_mt_engine , true );

        $this->template->warningPollingInterval = 1000 * ( INIT::$WARNING_POLLING_INTERVAL );

        if ( array_key_exists( explode( '-', $this->target_code )[0] , CatUtils::$cjk ) ) {
            $this->template->segmentQACheckInterval = 3000 * ( INIT::$SEGMENT_QA_CHECK_INTERVAL );
        } else {
            $this->template->segmentQACheckInterval = 1000 * ( INIT::$SEGMENT_QA_CHECK_INTERVAL );
        }


        $this->template->maxFileSize    = INIT::$MAX_UPLOAD_FILE_SIZE;
        $this->template->maxTMXFileSize = INIT::$MAX_UPLOAD_TMX_FILE_SIZE;

        $this->template->tagLockCustomizable  = ( INIT::$UNLOCKABLE_TAGS == true ) ? true : false;
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

        $this->template->isGDriveProject = $this->isCurrentProjectGDrive();

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

    public function getPassword(){
        return $this->password;
    }

    /**
     * Returns number indicating the current revision phase.
     * Returns null when in translate page.
     *
     * @return int|null
     */
    public function getRevisionNumber() {
        return catController::isRevision() ? (
                $this->revision == null ? 1 : $this->revision
        ) : null ;
    }

    public function getQaOverall() {
        // TODO: is this str_replace really required?
        return str_replace( ' ', '', $this->qa_overall );
    }

    public function isCurrentProjectGDrive() {
        return \Projects_ProjectDao::isGDriveProject($this->chunk->id_project);
    }

}
