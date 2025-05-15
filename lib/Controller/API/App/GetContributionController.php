<?php

namespace API\App;

use AbstractControllers\KleinController;
use API\Commons\Validators\LoginValidator;
use Chunks_ChunkDao;
use Contribution\ContributionRequestStruct;
use Contribution\Request;
use Exception;
use FeatureSet;
use Files\FilesPartsDao;
use INIT;
use InvalidArgumentException;
use Jobs\MetadataDao;
use Klein\Response;
use Matecat\SubFiltering\MateCatFilter;
use Projects_MetadataDao;
use Segments_SegmentDao;
use Segments_SegmentOriginalDataDao;
use TmKeyManagement_Filter;
use Users_UserDao;

class GetContributionController extends KleinController {

    protected $id_job;
    protected $received_password;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function get(): Response {
        try {
            $request = $this->validateTheRequest();

            $id_client = $request['id_client'];
            $id_job = $request['id_job'];
            $id_segment = $request['id_segment'];
            $num_results = $request['num_results'];
            $text = $request['text'];
            $id_translator = $request['id_translator'];
            $password = $request['password'];
            $received_password = $request['received_password'];
            $concordance_search = $request['concordance_search'];
            $switch_languages = $request['switch_languages'];
            $context_before = $request['context_before'];
            $context_after = $request['context_after'];
            $context_list_before = $request['context_list_before'];
            $context_list_after = $request['context_list_after'];
            $id_before = $request['id_before'];
            $id_after = $request['id_after'];
            $cross_language = $request['cross_language'];

            if ( $id_translator == 'unknown_translator' ) {
                $id_translator = "";
            }

            if ( empty( $num_results ) ) {
                $num_results = INIT::$DEFAULT_NUM_RESULTS_FROM_TM;
            }

            $jobStruct = Chunks_ChunkDao::getByIdAndPassword( $id_job, $password );
            $dataRefMap = Segments_SegmentOriginalDataDao::getSegmentDataRefMap( $id_segment );

            $projectStruct = $jobStruct->getProject();
            $this->featureSet->loadForProject( $projectStruct );

            $contributionRequest = new ContributionRequestStruct();

            if ( !$concordance_search ) {

                $this->rewriteContributionContexts( $jobStruct->source, $jobStruct->target, $request );

                $contributionRequest->mt_evaluation =
                        (bool)$projectStruct->getMetadataValue( Projects_MetadataDao::MT_EVALUATION ) ??
                        //TODO REMOVE after a reasonable amount of time, this is for back compatibility, previously the mt_evaluation flag was on jobs metadata
                        (bool)( new MetadataDao() )->get( $id_job, $received_password, Projects_MetadataDao::MT_EVALUATION, 60 * 60 ) ?? // for back compatibility, the mt_evaluation flag was on job metadata
                        false;

            }

            $file = (new FilesPartsDao())->getBySegmentId($id_segment);
            $owner = (new Users_UserDao())->getProjectOwner( $id_job );

            $contributionRequest                    = new ContributionRequestStruct();
            $contributionRequest->id_file           = $file->id_file;
            $contributionRequest->id_job            = $id_job;
            $contributionRequest->password          = $received_password;
            $contributionRequest->user              = $owner;
            $contributionRequest->dataRefMap        = $dataRefMap;
            $contributionRequest->contexts          = [
                'context_before' => $context_before,
                'segment'        => $text,
                'context_after'  => $context_after
            ];

            $contributionRequest->context_list_before = $context_list_before;
            $contributionRequest->context_list_after  = $context_list_after;

            $contributionRequest->jobStruct                  = $jobStruct;
            $contributionRequest->projectStruct              = $projectStruct;
            $contributionRequest->segmentId                  = $id_segment;
            $contributionRequest->id_client                  = $id_client;
            $contributionRequest->concordanceSearch          = $concordance_search;
            $contributionRequest->fromTarget                 = $switch_languages;
            $contributionRequest->resultNum                  = $num_results;
            $contributionRequest->crossLangTargets           = $this->getCrossLanguages( $cross_language );
            $contributionRequest->mt_quality_value_in_editor = $projectStruct->getMetadataValue( Projects_MetadataDao::MT_QUALITY_VALUE_IN_EDITOR ) ?? false;

            if ( $this->isRevision() ) {
                $contributionRequest->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
            } else {
                $contributionRequest->userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;
            }

            $jobsMetadataDao = new MetadataDao();
            $dialect_strict  = $jobsMetadataDao->get( $jobStruct->id, $jobStruct->password, 'dialect_strict', 10 * 60 );
            $mt_evaluation  = $jobsMetadataDao->get( $jobStruct->id, $jobStruct->password, 'mt_evaluation', 10 * 60 );

            if ( $dialect_strict !== null ) {
                $contributionRequest->dialect_strict = $dialect_strict->value == 1;
            }

            if ( $mt_evaluation !== null ) {
                $contributionRequest->mt_evaluation = $mt_evaluation->value == 1;
            }

            $tm_prioritization  = $jobsMetadataDao->get( $jobStruct->id, $jobStruct->password, 'tm_prioritization', 10 * 60 );

            if ( $tm_prioritization !== null ) {
                $contributionRequest->tm_prioritization = $tm_prioritization->value == 1;
            }

            if($contributionRequest->concordanceSearch){
                $contributionRequest->resultNum = 10;
            }

            // penalty_key
            $penalty_key = [];
            $tmKeys = json_decode( $jobStruct->tm_keys, true );

            foreach ($tmKeys as $tmKey){
                if(isset($tmKey['penalty']) and is_numeric($tmKey['penalty'])){
                    $penalty_key[] = $tmKey['penalty'];
                } else {
                    $penalty_key[] = 0;
                }
            }

            if(!empty($penalty_key)){
                $contributionRequest->penalty_key = $penalty_key;
            }

            Request::contribution( $contributionRequest );

            return $this->response->json([
                'errors' => [],
                'data' => [
                    "message" => "OK",
                    "id_client" => $id_client,
                    "request" => [
                        'session_id' => $contributionRequest->getSessionId(),
                        'id_file' => (int)$contributionRequest->id_file,
                        'id_job' => (int)$contributionRequest->id_job,
                        'password' => $contributionRequest->password,
                        'contexts' => $contributionRequest->contexts,
                        'id_client' => $contributionRequest->id_client,
                        'userRole' => $contributionRequest->userRole,
                        'tm_prioritization' => $contributionRequest->tm_prioritization,
                        'mt_evaluation' => $contributionRequest->mt_evaluation,
                        'penalty_key' => $contributionRequest->penalty_key,
                        'crossLangTargets' => $contributionRequest->crossLangTargets,
                        'fromTarget' => $contributionRequest->fromTarget,
                        'dialect_strict' => $contributionRequest->dialect_strict,
                        'segmentId' => $contributionRequest->segmentId ? (string)$contributionRequest->segmentId : null,
                        'resultNum' => (int)$contributionRequest->resultNum,
                        'concordanceSearch' => $contributionRequest->concordanceSearch,
                    ]
                ]
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array
     */
    private function validateTheRequest() {
        $id_client           = filter_var( $this->request->param( 'id_client' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $id_job              = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $id_segment          = filter_var( $this->request->param( 'id_segment' ), FILTER_SANITIZE_NUMBER_INT );
        $num_results         = filter_var( $this->request->param( 'num_results' ), FILTER_SANITIZE_NUMBER_INT );
        $text                = filter_var( $this->request->param( 'text' ), FILTER_UNSAFE_RAW );
        $id_translator       = filter_var( $this->request->param( 'id_translator' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $password            = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $received_password   = filter_var( $this->request->param( 'current_password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $concordance_search  = filter_var( $this->request->param( 'is_concordance' ), FILTER_VALIDATE_BOOLEAN );
        $switch_languages    = filter_var( $this->request->param( 'from_target' ), FILTER_VALIDATE_BOOLEAN );
        $context_before      = filter_var( $this->request->param( 'context_before' ), FILTER_UNSAFE_RAW );
        $context_after       = filter_var( $this->request->param( 'context_after' ), FILTER_UNSAFE_RAW );
        $context_list_before = filter_var( $this->request->param( 'context_list_before' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES ] );
        $context_list_after  = filter_var( $this->request->param( 'context_list_after' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES ] );
        $id_before           = filter_var( $this->request->param( 'id_before' ), FILTER_SANITIZE_NUMBER_INT );
        $id_after            = filter_var( $this->request->param( 'id_after' ), FILTER_SANITIZE_NUMBER_INT );
        $cross_language      = filter_var( $this->request->param( 'cross_language' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FORCE_ARRAY ] );
        $text                = trim( $text );

        if ( !$concordance_search ) {
            //execute these lines only in segment contribution search,
            //in case of user concordance search skip these lines
            //because segment can be optional
            if ( empty( $id_segment ) ) {
                throw new InvalidArgumentException("missing id_segment", -1);
            }
        }

        if ( is_null( $text ) or $text === '' ) {
            throw new InvalidArgumentException("missing text", -2);
        }

        if ( empty( $id_job ) ) {
            throw new InvalidArgumentException("missing id job", -3);
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException("missing job password", -4);
        }

        if ( empty( $id_client ) ) {
            throw new InvalidArgumentException("missing id_client", -5);
        }

        $this->id_job = $id_job;
        $this->received_password = $received_password;

        return [
            'id_client' => $id_client,
            'id_job' => $id_job,
            'id_segment' => $id_segment,
            'num_results' => $num_results,
            'text' => $text,
            'id_translator' => $id_translator,
            'password' => $password,
            'received_password' => $received_password,
            'concordance_search' => $concordance_search,
            'switch_languages' => $switch_languages,
            'context_before' => $context_before,
            'context_after' => $context_after,
            'id_before' => $id_before,
            'id_after' => $id_after,
            'cross_language' => $cross_language,
            'context_list_after' => json_decode( $context_list_after, true ),
            'context_list_before' => json_decode( $context_list_before, true ),
        ];
    }

    /**
     * @param $source
     * @param $target
     * @param $request
     *
     * @throws Exception
     */
    private function rewriteContributionContexts( $source, $target, &$request ): void {
        $featureSet = ( $this->featureSet !== null ) ? $this->featureSet : new FeatureSet();

        //Get contexts
        $segmentsList = ( new Segments_SegmentDao )->setCacheTTL( 60 * 60 * 24 )->getContextAndSegmentByIDs(
            [
                'id_before'  => $request['id_before'],
                'id_segment' => $request['id_segment'],
                'id_after'   => $request['id_after']
            ]
        );

        $featureSet->filter( 'rewriteContributionContexts', $segmentsList, $request );

        $Filter = MateCatFilter::getInstance( $featureSet, $source, $target, [] );

        if ( $segmentsList->id_before ) {
            $request['context_before'] = $Filter->fromLayer0ToLayer1( $segmentsList->id_before->segment );
        }

        if ( $segmentsList->id_segment ) {
            $request['text'] = $Filter->fromLayer0ToLayer1( $segmentsList->id_segment->segment );
        }

        if ( $segmentsList->id_after ) {
            $request['context_after'] = $Filter->fromLayer0ToLayer1( $segmentsList->id_after->segment );
        }
    }

    /**
     * Remove voids
     * ("en-GB," => [0 => 'en-GB'])
     *
     * @param $cross_language
     *
     * @return array
     */
    private function getCrossLanguages( $cross_language ): array {
        return !empty( $cross_language ) ? explode( ",", rtrim( $cross_language[ 0 ], ',' ) ) : [];
    }
}