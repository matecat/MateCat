<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.42
 *
 */

namespace API\V2\Json;


use API\App\Json\OutsourceConfirmation;
use CatUtils;
use Chunks_ChunkStruct;
use DataAccess\ShapelessConcreteStruct;
use Features\ReviewExtended;
use Features\ReviewImproved;
use Langs_Languages;
use Langs_LanguageDomains;
use LQA\ChunkReviewDao;
use ManageUtils;
use Routes;
use TmKeyManagement_ClientTmKeyStruct;
use Users_UserStruct;
use Utils;
use WordCount_Struct;
use FeatureSet;

class Job {

    /**
     * @var \Users_UserStruct
     */
    protected $user;

    /**
     * @var bool
     */
    protected $called_from_api = false;

    /**
     * @var TmKeyManagement_ClientTmKeyStruct[]
     */
    protected $keyList = [];

    /**
     * @param \Users_UserStruct $user
     *
     * @return $this
     */
    public function setUser( Users_UserStruct $user = null ) {
        $this->user = $user;
        return $this;
    }

    /**
     * @param bool $called_from_api
     *
     * @return $this
     */
    public function setCalledFromApi( $called_from_api ) {
        $this->called_from_api = (bool)$called_from_api;
        return $this;
    }

    /**
     * @param Chunks_ChunkStruct $jStruct
     *
     * @return array
     */
    protected function getKeyList( Chunks_ChunkStruct $jStruct ) {

        if( empty( $this->user ) ){
            return [];
        }

        if ( !$this->called_from_api ) {
            $out = $jStruct->getClientKeys( $this->user, \TmKeyManagement_Filter::OWNER )[ 'job_keys' ];
        } else {
            $out = $jStruct->getClientKeys( $this->user, \TmKeyManagement_Filter::ROLE_TRANSLATOR )[ 'job_keys' ];
        }

        return ( new JobClientKeys( $out ) )->render();

    }

    /**
     * @param $jStruct Chunks_ChunkStruct
     *
     * @return array
     * @throws \Exception
     * @throws \Exceptions\NotFoundError
     */
    public function renderItem( Chunks_ChunkStruct $jStruct ) {

        $featureSet = new FeatureSet();

        $outsourceInfo = $jStruct->getOutsource();
        $tStruct       = $jStruct->getTranslator();
        $outsource     = null;
        $translator    = null;
        if ( !empty( $outsourceInfo ) ) {
            $outsource = ( new OutsourceConfirmation( $outsourceInfo ) )->render();
        } else {
            $translator = ( !empty( $tStruct ) ? ( new JobTranslator() )->renderItem( $tStruct ) : null );
        }

        $jobStats = new WordCount_Struct();
        $jobStats->setIdJob( $jStruct->id );
        $jobStats->setDraftWords( $jStruct->draft_words + $jStruct->new_words ); // (draft_words + new_words) AS DRAFT
        $jobStats->setRejectedWords( $jStruct->rejected_words );
        $jobStats->setTranslatedWords( $jStruct->translated_words );
        $jobStats->setApprovedWords( $jStruct->approved_words );

        $lang_handler = Langs_Languages::getInstance();

        $subject_handler = Langs_LanguageDomains::getInstance();
        $subjects        = $subject_handler->getEnabledDomains();

        $subjects_keys = Utils::array_column( $subjects, "key" );
        $subject_key   = array_search( $jStruct->subject, $subjects_keys );

        $warningsCount = $jStruct->getWarningsCount();

        $featureSet->loadForProject($jStruct->getJob()->getProject());

        if(in_array(ReviewImproved::FEATURE_CODE, $featureSet->getCodes()) || in_array(ReviewExtended::FEATURE_CODE, $featureSet->getCodes())){
            $reviseIssues = [];

        } else{

            $reviseClass = new \Constants_Revise();

            $jobQA = new \Revise_JobQA(
                    $jStruct->id,
                    $jStruct->password,
                    $jobStats->getTotal(),
                    $reviseClass
            );

            list( $jobQA, $reviseClass ) = $featureSet->filter( "overrideReviseJobQA", [ $jobQA, $reviseClass ], $jStruct->id,
                    $jStruct->password,
                    $jobStats->getTotal() );

            /**
             * @var $jobQA \Revise_JobQA
             */
            $jobQA->retrieveJobErrorTotals();
            $jobQA->evalJobVote();
            $qa_data      = $jobQA->getQaData();

            $reviseIssues = [];
            foreach ( $qa_data as $issue ) {
                $reviseIssues[ str_replace( " " , "_", strtolower( $issue[ 'type' ] ) ) ] = [
                        'allowed' => $issue[ 'allowed' ],
                        'found'   => $issue[ 'found' ]
                ];
            }
        }

        $result = [
                'id'                    => (int)$jStruct->id,
                'password'              => $jStruct->password,
                'source'                => $jStruct->source,
                'target'                => $jStruct->target,
                'sourceTxt'             => $lang_handler->getLocalizedName( $jStruct->source ),
                'targetTxt'             => $lang_handler->getLocalizedName( $jStruct->target ),
                'status'                => $jStruct->status_owner,
                'subject'               => $jStruct->subject,
                'subject_printable'     => $subjects[$subject_key]['display'],
                'owner'                 => $jStruct->owner,
                'open_threads_count'    => (int)$jStruct->getOpenThreadsCount(),
                'create_timestamp'      => strtotime( $jStruct->create_date ),
                'created_at'            => Utils::api_timestamp( $jStruct->create_date ),
                'create_date'           => $jStruct->create_date,
                'formatted_create_date' => ManageUtils::formatJobDate( $jStruct->create_date ),
                'quality_overall'       => CatUtils::getQualityOverallFromJobStruct( $jStruct ),
                'pee'                   => $jStruct->getPeeForTranslatedSegments(),
                'private_tm_key'        => $this->getKeyList( $jStruct ),
                'warnings_count'        => $warningsCount->warnings_count,
                'warning_segments'      => ( isset( $warningsCount->warning_segments ) ? $warningsCount->warning_segments : [] ),
                'stats'                 => CatUtils::getFastStatsForJob( $jobStats, false ),
                'outsource'             => $outsource,
                'translator'            => $translator,
                'total_raw_wc'          => (int)$jStruct->total_raw_wc,
                'quality_summary'       => [
                        'equivalent_class' => $jStruct->getQualityInfo(),
                        'quality_overall'  => $jStruct->getQualityOverall(),
                        'errors_count'     => (int)$jStruct->getErrorsCount(),
                        'revise_issues' => $reviseIssues
                ],

        ];


        $project = $jStruct->getProject();

        /**
         * @var $projectData ShapelessConcreteStruct[]
         */
        $projectData = ( new \Projects_ProjectDao() )->setCacheTTL( 60 * 60 * 24 )->getProjectData( $project->id, $project->password );

        $formatted = new ProjectUrls( $projectData );

        /** @var $formatted ProjectUrls */
        $formatted = $project->getFeatures()->filter( 'projectUrls', $formatted );

        $urlsObject = $formatted->render( true );
        $result[ 'urls' ] = $urlsObject[ 'jobs' ][ $jStruct->id ][ 'chunks' ][ $jStruct->password ];

        $result[ 'urls' ][ 'original_download_url' ]    = $urlsObject[ 'jobs' ][ $jStruct->id ][ 'original_download_url' ];
        $result[ 'urls' ][ 'translation_download_url' ] = $urlsObject[ 'jobs' ][ $jStruct->id ][ 'translation_download_url' ];
        $result[ 'urls' ][ 'xliff_download_url' ]       = $urlsObject[ 'jobs' ][ $jStruct->id ][ 'xliff_download_url' ];

        return $result;

    }

}