<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use Engines_Intento as Intento;
use Exceptions\AuthorizationError;
use Exceptions\NotFoundException;
use LQA\ChunkReviewStruct;
use Teams\MembershipStruct;
use WordCount\WordCountStruct;

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
    protected $currentPassword = "";
    private   $create_date     = "";

    private $start_time = 0.00;

    private $job_stats = [];
    private $job_owner = "";

    private $job_not_found = false;
    private $job_archived  = false;
    private $job_cancelled = false;

    private $qa_data = '[]';

    private $qa_overall = '';

    public  $target_code;
    public  $source_code;
    private $revision;

    /**
     * @var Jobs_JobStruct
     */
    private $chunk;

    /**
     * @var ?Projects_ProjectStruct
     */
    public $project;

    private $translation_engines;

    private $mt_id;

    /**
     * @var WordCountStruct
     */
    private $wStruct;

    protected $templateName = "index.html";

    public function __construct() {
        $this->start_time = microtime( 1 ) * 1000;

        parent::__construct();
        $this->checkLoginRequiredAndRedirect();
        parent::makeTemplate( $this->templateName );

        $filterArgs = [
                'jid'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'revision' => [ 'filter' => FILTER_VALIDATE_INT ],
        ];

        $getInput = (object)filter_input_array( INPUT_GET, $filterArgs );

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

        $this->featureSet->loadForProject( $this->project );
    }

    /**
     * @return mixed|void
     * @throws \Exception
     */
    public function doAction() {

        $this->featureSet->run( 'beginDoAction', $this );

        try {
            $this->findJobByIdPasswordAndSourcePage();
        } catch ( NotFoundException $e ) {
            $this->job_not_found = true;

            return;
        }

        //retrieve job owner. It will be useful also if the job is archived or cancelled
        $this->job_owner = ( $this->chunk->owner != "" ) ? $this->chunk->owner : INIT::$MAILER_RETURN_PATH;

        if ( $this->chunk->isCanceled() ) {
            $this->job_cancelled = true;

            return;
        }

        if ( $this->chunk->isArchived() ) {
            $this->job_archived = true;

            return;
        }

        if ( $this->chunk->isDeleted() ) {
            $this->job_not_found = true;

            return;
        }

        $this->pid         = $this->project->id;
        $this->cid         = $this->project->id_customer;
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

        $engine = new EnginesModel_EngineDAO( Database::obtain() );

        //this gets all engines of the user
        if ( $this->isLoggedIn() ) {
            $engineQuery         = new EnginesModel_EngineStruct();
            $engineQuery->type   = Constants_Engines::MT;
            $engineQuery->uid    = $this->user->uid;
            $engineQuery->active = 1;
            $mt_engines          = $engine->read( $engineQuery );
        } else {
            $mt_engines = [];
        }

        // this gets MyMemory
        $engineQuery         = new EnginesModel_EngineStruct();
        $engineQuery->type   = Constants_Engines::TM;
        $engineQuery->active = 1;
        $tms_engine          = $engine->setCacheTTL( 3600 * 24 * 30 )->read( $engineQuery );

        //this gets MT engine active for the job
        $engineQuery         = new EnginesModel_EngineStruct();
        $engineQuery->id     = $this->chunk->id_mt_engine;
        $engineQuery->active = 1;
        $active_mt_engine    = $engine->setCacheTTL( 60 * 10 )->read( $engineQuery );

        $active_mt_engine_array = [];
        if ( !empty( $active_mt_engine ) ) {
            $engine_type            = explode( "\\", $active_mt_engine[ 0 ]->class_load );
            $active_mt_engine_array = [
                    "id"          => $active_mt_engine[ 0 ]->id,
                    "name"        => $active_mt_engine[ 0 ]->name,
                    "type"        => $active_mt_engine[ 0 ]->type,
                    "description" => $active_mt_engine[ 0 ]->description,
                    'engine_type' => ( $active_mt_engine[ 0 ]->class_load === 'MyMemory' ? 'MMTLite' : array_pop( $engine_type ) ),
            ];
        }

        $this->template->active_engine = $active_mt_engine_array;

        /*
         * array_unique cast EnginesModel_EngineStruct to string
         *
         * EnginesModel_EngineStruct implements __toString method
         *
         */
        $this->translation_engines = array_unique( array_merge( $active_mt_engine, $tms_engine, $mt_engines ) );
        $this->translation_engines = $this->removeMMTFromEngines( $this->translation_engines );

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
            $chunkReviewStruct     = $this->featureSet->filter(
                    'filter_review_password_to_job_password',
                    new ChunkReviewStruct( [
                            'password'        => $this->received_password,
                            'review_password' => $this->received_password,
                            'id_job'          => $this->jid
                    ] ),
                    Utils::getSourcePage()
            );
            $this->chunk           = $chunkReviewStruct->getChunk();
            $this->password        = $chunkReviewStruct->password;
            $this->review_password = $chunkReviewStruct->review_password;
        } else {
            $this->password        = $this->received_password;
            $this->review_password = $this->password;
            $this->chunk           = Chunks_ChunkDao::getByIdAndPassword( $this->jid, $this->password );
        }

        $this->currentPassword = $this->review_password;
    }

    protected function _saveActivity() {

        if ( $this->isRevision() ) {
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
     * @throws Exception
     */
    public function setTemplateVars() {

        $ownerMail    = INIT::$SUPPORT_MAIL;
        $jobOwnerIsMe = false;

        if ( $this->job_cancelled || $this->job_archived ) {

            $team = $this->project->getTeam();

            if ( !empty( $team ) ) {

                $teamModel = new TeamModel( $team );
                $teamModel->updateMembersProjectsCount();
                $membersIdList = [];
                if ( $team->type == Constants_Teams::PERSONAL ) {
                    $ownerMail = $team->getMembers()[ 0 ]->getUser()->getEmail();
                } else {
                    $assignee = ( new Users_UserDao() )->setCacheTTL( 60 * 60 * 24 )->getByUid( $this->project->id_assignee );

                    if ( $assignee ) {
                        $ownerMail = $assignee->getEmail();
                    } else {
                        $ownerMail = INIT::$SUPPORT_MAIL;
                    }

                    $membersIdList = array_map( function ( $memberStruct ) {
                        /**
                         * @var $memberStruct MembershipStruct
                         */
                        return $memberStruct->uid;
                    }, $team->getMembers() );

                }

                if ( $this->user->email == $ownerMail || in_array( $this->user->uid, $membersIdList ) ) {
                    $jobOwnerIsMe = true;
                }

            }

        }

        if ( $this->job_not_found ) {
            $controllerInstance = new CustomPageView();
            $controllerInstance->setView( 'job_not_found.html', [ "support_mail" => INIT::$SUPPORT_MAIL ], 404 );
            $controllerInstance->render();
        }

        if ( $this->job_cancelled ) {
            $controllerInstance = new CustomPageView();
            $controllerInstance->setView( 'job_cancelled.html', [
                    "support_mail" => INIT::$SUPPORT_MAIL,
                    "owner_email"  => $ownerMail
            ] );
            $controllerInstance->render();
        }

        if ( $this->job_archived ) {
            $controllerInstance = new CustomPageView();
            $controllerInstance->setView( 'job_archived.html', [
                    "support_mail" => INIT::$SUPPORT_MAIL,
                    "owner_email"  => $ownerMail,
                    'jid'          => $this->jid,
                    'password'     => $this->password,
                    'jobOwnerIsMe' => $jobOwnerIsMe
            ] );
            $controllerInstance->render();
        }

        $this->template->jid             = $this->jid;
        $this->template->currentPassword = $this->currentPassword;

        $this->template->id_team = null;

        $this->template->pid         = $this->pid;
        $this->template->source_code = $this->source_code;
        $this->template->target_code = $this->target_code;

        if ( !empty( $this->project->id_team ) ) {
            $this->template->id_team = $this->project->id_team;

            if ( !isset( $team ) ) {
                $team = $this->project->getTeam();
            }

            $this->template->team_name = $team->name;
        }

        $this->template->owner_email        = $this->job_owner;
        $this->template->jobOwnerIsMe       = ( $this->user->email == $this->job_owner );
        $this->template->get_public_matches = ( !$this->chunk->only_private_tm );
        $this->template->job_not_found      = $this->job_not_found;
        $this->template->job_archived       = ( $this->job_archived ) ? 1 : '';
        $this->template->job_cancelled      = $this->job_cancelled;

        $this->template->cid         = $this->cid;
        $this->template->create_date = $this->create_date;
        $this->template->pname       = $this->project->name;

        $this->template->mt_engines                            = $this->translation_engines;
        $this->template->translation_engines_intento_providers = Intento::getProviderList();

        $this->template->not_empty_default_tm_key = !empty( INIT::$DEFAULT_TM_KEY );

        $this->template->word_count_type        = $this->project->getWordCountType();
        $this->job_stats[ 'analysis_complete' ] = ( $this->project->status_analysis == Constants_ProjectStatus::STATUS_DONE ? true : false );

        $this->template->stat_quality = $this->qa_data;

        $this->template->overall_quality_class = strtolower( $this->getQaOverall() );

        $end_time                  = microtime( true ) * 1000;
        $load_time                 = $end_time - $this->start_time;
        $this->template->load_time = $load_time;

        $this->template->first_job_segment = $this->chunk->job_first_segment;
        $this->template->tms_enabled       = var_export( (bool)$this->chunk->id_tms, true );
        $this->template->mt_enabled        = var_export( (bool)$this->chunk->id_mt_engine, true );

        $this->template->warningPollingInterval = 1000 * ( INIT::$WARNING_POLLING_INTERVAL );

        if ( array_key_exists( explode( '-', $this->target_code )[ 0 ], CatUtils::$cjk ) ) {
            $this->template->segmentQACheckInterval = 3000 * ( INIT::$SEGMENT_QA_CHECK_INTERVAL );
        } else {
            $this->template->segmentQACheckInterval = 1000 * ( INIT::$SEGMENT_QA_CHECK_INTERVAL );
        }


        $this->template->maxFileSize    = INIT::$MAX_UPLOAD_FILE_SIZE;
        $this->template->maxTMXFileSize = INIT::$MAX_UPLOAD_TMX_FILE_SIZE;

        $this->template->tagLockCustomizable = ( INIT::$UNLOCKABLE_TAGS == true ) ? true : false;
        $this->template->copySourceInterval  = INIT::$COPY_SOURCE_INTERVAL;

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
            $this->template->socket_base_url  = INIT::$SOCKET_BASE_URL;
        }

        $projectMetaDataDao              = new Projects_MetadataDao();
        $projectMetaData                 = $projectMetaDataDao->get( $this->project->id, Projects_MetadataDao::FEATURES_KEY );
        $this->template->project_plugins = ( !empty( $projectMetaData ) ) ? $this->featureSet->filter( 'appendInitialTemplateVars', explode( ",", $projectMetaData->value ) ) : [];

        //Maybe some plugin want to disable the Split from the config
        $this->template->splitSegmentEnabled = 'true';

        $this->intOauthClients();

        $this->decorator = new CatDecorator( $this, $this->template );
        $this->decorator->decorate();

        $this->featureSet->appendDecorators(
                'CatDecorator',
                $this,
                $this->template
        );
    }

    public function getJobStats() {
        return $this->job_stats;
    }

    /**
     * @return Jobs_JobStruct
     */
    public function getChunk() {
        return $this->chunk;
    }

    /**
     * @return string
     */
    public function getReviewPassword() {
        return $this->review_password;
    }

    /**
     * @return string
     */
    public function getPassword() {
        return ( $this->chunk !== null ) ? $this->chunk->password : $this->password;
    }

    /**
     * @return bool
     */
    public function isJobSplitted() {
        return ( $this->chunk !== null ) ? $this->chunk->isSplitted() : false;
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
        ) : null;
    }

    public function getQaOverall() {
        // TODO: is this str_replace really required?
        return str_replace( ' ', '', $this->qa_overall );
    }

    public function isCurrentProjectGDrive() {
        return Projects_ProjectDao::isGDriveProject( $this->chunk->id_project );
    }

}
