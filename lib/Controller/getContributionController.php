<?php

use Contribution\ContributionRequestStruct;
use Contribution\Request;
use Matecat\SubFiltering\MateCatFilter;

class getContributionController extends ajaxController {

    protected $id_job;
    protected $password;
    protected $id_segment;
    protected $id_client;
    private   $concordance_search;
    private   $switch_languages;
    private   $num_results;
    private   $text;
    private   $id_translator;

    protected $context_before;
    protected $context_after;
    protected $id_before;
    protected $id_after;

    private $__postInput;
    private $cross_language;

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'id_segment'       => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_job'           => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'num_results'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'text'             => [ 'filter' => FILTER_UNSAFE_RAW ],
                'id_translator'    => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'password'         => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'current_password' => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'is_concordance'   => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'from_target'      => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'context_before'   => [ 'filter' => FILTER_UNSAFE_RAW ],
                'context_after'    => [ 'filter' => FILTER_UNSAFE_RAW ],
                'id_before'        => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_after'         => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_client'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'cross_language'   => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FORCE_ARRAY ]
        ];

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
//        $this->__postInput = filter_var_array( $_REQUEST, $filterArgs );

        $this->id_segment = $this->__postInput[ 'id_segment' ];
        $this->id_before  = $this->__postInput[ 'id_before' ];
        $this->id_after   = $this->__postInput[ 'id_after' ];

        $this->id_job             = $this->__postInput[ 'id_job' ];
        $this->num_results        = $this->__postInput[ 'num_results' ];
        $this->text               = trim( $this->__postInput[ 'text' ] );
        $this->id_translator      = $this->__postInput[ 'id_translator' ];
        $this->concordance_search = $this->__postInput[ 'is_concordance' ];
        $this->switch_languages   = $this->__postInput[ 'from_target' ];
        $this->password           = $this->__postInput[ 'password' ];
        $this->received_password  = $this->__postInput[ 'current_password' ];
        $this->id_client          = $this->__postInput[ 'id_client' ];
        $this->cross_language     = $this->__postInput[ 'cross_language' ];

        if ( $this->id_translator == 'unknown_translator' ) {
            $this->id_translator = "";
        }

    }

    public function doAction() {

        if ( !$this->concordance_search ) {
            //execute these lines only in segment contribution search,
            //in case of user concordance search skip these lines
            //because segment can be optional
            if ( empty( $this->id_segment ) ) {
                $this->result[ 'errors' ][] = [ "code" => -1, "message" => "missing id_segment" ];
            }
        }

        if ( is_null( $this->text ) || $this->text === '' ) {
            $this->result[ 'errors' ][] = [ "code" => -2, "message" => "missing text" ];
        }

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][] = [ "code" => -3, "message" => "missing id_job" ];
        }

        if ( empty( $this->id_client ) ) {
            $this->result[ 'errors' ][] = [ "code" => -4, "message" => "missing id_client" ];
        }

        if ( empty( $this->num_results ) ) {
            $this->num_results = INIT::$DEFAULT_NUM_RESULTS_FROM_TM;
        }

        if ( !empty( $this->result[ 'errors' ] ) ) {
            return -1;
        }

//        throw new \Exceptions\NotFoundException( "Record Not Found" );
        //get Job Info, we need only a row of jobs ( split )
        $jobStruct = Chunks_ChunkDao::getByIdAndPassword( $this->id_job, $this->password );

        $dataRefMap = Segments_SegmentOriginalDataDao::getSegmentDataRefMap( $this->id_segment );

        $projectStruct = $jobStruct->getProject();
        $this->featureSet->loadForProject( $projectStruct );

        $this->readLoginInfo();
        if ( !$this->concordance_search ) {
            $this->_getContexts( $jobStruct->source, $jobStruct->target );
        }

        $contributionRequest                    = new ContributionRequestStruct();
        $contributionRequest->user              = $this->user;
        $contributionRequest->dataRefMap        = $dataRefMap;
        $contributionRequest->contexts          = [
                'context_before' => $this->context_before,
                'segment'        => $this->text,
                'context_after'  => $this->context_after
        ];
        $contributionRequest->jobStruct         = $jobStruct;
        $contributionRequest->projectStruct     = $projectStruct;
        $contributionRequest->segmentId         = $this->id_segment;
        $contributionRequest->id_client         = $this->id_client;
        $contributionRequest->concordanceSearch = $this->concordance_search;
        $contributionRequest->fromTarget        = $this->switch_languages;
        $contributionRequest->resultNum         = $this->num_results;
        $contributionRequest->crossLangTargets  = $this->getCrossLanguages();

        if ( self::isRevision() ) {
            $contributionRequest->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        } else {
            $contributionRequest->userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
        }

        Request::contribution( $contributionRequest );

        $this->result = [ "errors" => [], "data" => [ "message" => "OK", "id_client" => $this->id_client ] ];

    }

    /**
     * Remove voids
     * ("en-GB," => [0 => 'en-GB'])
     *
     * @return array
     */
    private function getCrossLanguages() {
        return !empty( $this->cross_language ) ? explode( ",", rtrim( $this->cross_language[ 0 ], ',' ) ) : [];
    }

    /**
     * @param string $source
     * @param string $target
     *
     * @throws \Exception
     */
    protected function _getContexts( $source, $target ) {

        $featureSet = ( $this->featureSet !== null ) ? $this->featureSet : new \FeatureSet();

        //Get contexts
        $segmentsList = ( new Segments_SegmentDao )->setCacheTTL( 60 * 60 * 24 )->getContextAndSegmentByIDs(
                [
                        'id_before'  => $this->id_before,
                        'id_segment' => $this->id_segment,
                        'id_after'   => $this->id_after
                ]
        );

        $featureSet->filter( 'rewriteContributionContexts', $segmentsList, $this->__postInput );

        $Filter = MateCatFilter::getInstance( $featureSet, $source, $target, [] );

        if ( $segmentsList->id_before ) {
            $this->context_before = $Filter->fromLayer0ToLayer1( $segmentsList->id_before->segment );
        }

        if ( $segmentsList->id_segment ) {
            $this->text = $Filter->fromLayer0ToLayer1( $segmentsList->id_segment->segment );
        }

        if ( $segmentsList->id_after ) {
            $this->context_after = $Filter->fromLayer0ToLayer1( $segmentsList->id_after->segment );
        }

    }

}

