<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 16:16
 */

namespace API\V3\Json;

use API\App\Json\OutsourceConfirmation;
use API\V2\Json\JobTranslator;
use API\V2\Json\ProjectUrls;
use CatUtils;
use Chunks_ChunkStruct;
use Constants;
use DataAccess\ShapelessConcreteStruct;
use Features\ReviewExtended\Model\QualityReportDao;
use Features\ReviewExtended\ReviewUtils;
use FeatureSet;
use Langs_LanguageDomains;
use Langs_Languages;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use Projects_ProjectStruct;
use RevisionFactory;
use Utils;
use WordCount_Struct;

class Chunk extends \API\V2\Json\Chunk {

    protected $chunk_reviews ;
    protected $chunk ;

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return array
     * @throws \Exception
     * @throws \Exceptions\NotFoundException
     */
    public function renderOne( Chunks_ChunkStruct $chunk ) {
        $project     = $chunk->getProject();
        $featureSet  = $project->getFeatures();
        return [
                'job' => [
                        'id'     => (int) $chunk->id,
                        'chunks' => [ $this->renderItem( $chunk, $project, $featureSet ) ]
                ]
        ];
    }

    /**
     * @param                         $chunk Chunks_ChunkStruct
     *
     * @param Projects_ProjectStruct  $project
     * @param FeatureSet              $featureSet
     *
     * @return array
     * @throws \Exception
     */
    public function renderItem( Chunks_ChunkStruct $chunk, Projects_ProjectStruct $project, FeatureSet $featureSet ) {

        $this->chunk = $chunk ;
        $outsourceInfo = $chunk->getOutsource();
        $tStruct       = $chunk->getTranslator();
        $outsource     = null;
        $translator    = null;
        if ( !empty( $outsourceInfo ) ) {
            $outsource = ( new OutsourceConfirmation( $outsourceInfo ) )->render();
        } else {
            $translator = ( !empty( $tStruct ) ? ( new JobTranslator() )->renderItem( $tStruct ) : null );
        }

        $jobStats = new WordCount_Struct();
        $jobStats->setIdJob( $chunk->id );
        $jobStats->setDraftWords( $chunk->draft_words + $chunk->new_words ); // (draft_words + new_words) AS DRAFT
        $jobStats->setRejectedWords( $chunk->rejected_words );
        $jobStats->setTranslatedWords( $chunk->translated_words );
        $jobStats->setApprovedWords( $chunk->approved_words );

        $lang_handler = Langs_Languages::getInstance();

        $subject_handler = Langs_LanguageDomains::getInstance();
        $subjects        = $subject_handler->getEnabledDomains();

        $subjects_keys = Utils::array_column( $subjects, "key" );
        $subject_key   = array_search( $chunk->subject, $subjects_keys );

        $warningsCount = $chunk->getWarningsCount();

        $result = [
                'id'                      => (int)$chunk->id,
                'password'                => $chunk->password,
                'source'                  => $chunk->source,
                'target'                  => $chunk->target,
                'sourceTxt'               => $lang_handler->getLocalizedName( $chunk->source ),
                'targetTxt'               => $lang_handler->getLocalizedName( $chunk->target ),
                'status'                  => $chunk->status_owner,
                'subject'                 => $chunk->subject,
                'subject_printable'       => $subjects[ $subject_key ][ 'display' ],
                'owner'                   => $chunk->owner,
                'total_time_to_edit'      => $chunk->total_time_to_edit,
                'avg_post_editing_effort' => $chunk->avg_post_editing_effort,
                'open_threads_count'      => (int)$chunk->getOpenThreadsCount(),
                'created_at'              => Utils::api_timestamp( $chunk->create_date ),
                'pee'                     => $chunk->getPeeForTranslatedSegments(),
                'private_tm_key'          => $this->getKeyList( $chunk ),
                'warnings_count'          => $warningsCount->warnings_count,
                'warning_segments'        => ( isset( $warningsCount->warning_segments ) ? $warningsCount->warning_segments : [] ),
                'stats'                   => $this->_getStats( $jobStats ) ,
                'outsource'               => $outsource,
                'translator'              => $translator,
                'total_raw_wc'            => (int) $chunk->total_raw_wc
        ];


        if ( $featureSet->hasRevisionFeature() ) {

            foreach( $this->getChunkReviews() as $index => $chunkReview ) {

                list( $passfail, $reviseIssues, $quality_overall, $is_pass, $score, $total_issues_weight, $total_reviewed_words_count, $categories ) =
                        $this->revisionQualityVars( $chunk, $project, $chunkReview );

                $result = $this->populateQualitySummarySection($result, $chunkReview->source_page,
                        $chunk, $quality_overall, $reviseIssues, $score, $categories,
                        $total_issues_weight, $total_reviewed_words_count, $passfail,
                        $chunkReview->total_tte,
                        $is_pass
                );

                $result = $this->populateRevisePasswords( $chunkReview, $result );

            }

        } else {
            $qualityInfoArray = CatUtils::getQualityInfoFromJobStruct( $chunk, $featureSet );

            list( $passfail, $reviseIssues, $quality_overall, $score, $total_issues_weight, $total_reviewed_words_count, $categories ) =
                    $this->legacyRevisionQualityVars( $chunk, $featureSet, $jobStats, $qualityInfoArray );

            $result = $this->populateQualitySummarySection($result, Constants::SOURCE_PAGE_REVISION,
                    $chunk, $quality_overall, $reviseIssues, $score, $categories,
                    $total_issues_weight, $total_reviewed_words_count, $passfail,
                    0 );
        }

        /**
         * @var $projectData ShapelessConcreteStruct[]
         */
        $projectData = ( new \Projects_ProjectDao() )->setCacheTTL( 60 * 60 * 24 )->getProjectData( $project->id, $project->password );

        $formatted = new ProjectUrls( $projectData );

        /** @var $formatted ProjectUrls */
        $formatted = $featureSet->filter( 'projectUrls', $formatted );

        $urlsObject       = $formatted->render( true );
        $result[ 'urls' ] = $urlsObject[ 'jobs' ][ $chunk->id ][ 'chunks' ][ $chunk->password ];

        $result[ 'urls' ][ 'original_download_url' ]    = $urlsObject[ 'jobs' ][ $chunk->id ][ 'original_download_url' ];
        $result[ 'urls' ][ 'translation_download_url' ] = $urlsObject[ 'jobs' ][ $chunk->id ][ 'translation_download_url' ];
        $result[ 'urls' ][ 'xliff_download_url' ]       = $urlsObject[ 'jobs' ][ $chunk->id ][ 'xliff_download_url' ];

        return $result;
    }

    protected function getChunkReviews() {
        if ( is_null( $this->chunk_reviews ) ) {
            $this->chunk_reviews = (new ChunkReviewDao() )->findChunkReviews( $this->chunk ) ;
        }
        return $this->chunk_reviews ;
    }

    /**
     * @param ChunkReviewStruct $chunk_review
     * @param                   $result
     *
     * @return mixed
     */
    protected function populateRevisePasswords( ChunkReviewStruct $chunk_review, $result ){

        if ( !isset( $result['revise_passwords'] ) ) {
            $result['revise_passwords'] = [];
        }

        if ( $chunk_review->source_page <= Constants::SOURCE_PAGE_REVISION ) {
            $result['revise_passwords'][] = [
                    'revision_number' => 1,
                    'password'        => $chunk_review->review_password
            ];
        } elseif ( $chunk_review->source_page > Constants::SOURCE_PAGE_REVISION ) {
            $result['revise_passwords'][] = [
                    'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $chunk_review->source_page ),
                    'password'        => $chunk_review->review_password
            ];
        }

        return $result;

    }

    /**
     * @param $result
     * @param $source_page
     * @param $jStruct
     * @param $quality_overall
     * @param $reviseIssues
     * @param $score
     * @param $categories
     * @param $total_issues_weight
     * @param $total_reviewed_words_count
     * @param $passfail
     *
     * @param $total_tte
     * @param $is_pass
     *
     * @return mixed
     */
    protected function populateQualitySummarySection( $result, $source_page, $jStruct, $quality_overall, $reviseIssues, $score, $categories,
                                                         $total_issues_weight, $total_reviewed_words_count, $passfail, $total_tte, $is_pass ) {

        if ( !isset( $result['quality_summary'] ) ) {
            $result['quality_summary'] = [];
        }

        $result['quality_summary'][] = [
                'revision_number'     => ReviewUtils::sourcePageToRevisionNumber( $source_page ),
                'equivalent_class'    => $jStruct->getQualityInfo(),
                'is_pass'             => $is_pass,
                'quality_overall'     => $quality_overall,
                'errors_count'        => (int)$jStruct->getErrorsCount(),
                'revise_issues'       => $reviseIssues,
                'score'               => floatval($score),
                'categories'          => $categories,
                'total_issues_weight' => $total_issues_weight,
                'total_reviewed_words_count' => (int)$total_reviewed_words_count,
                'passfail'            => $passfail,
                'total_time_to_edit'  => (int) $total_tte
        ];

        return $result ;
    }

    protected function _getStats( $jobStats ) {
        $stats = CatUtils::getPlainStatsForJobs( $jobStats );
        unset( $stats ['id'] );
        $stats = array_change_key_case( $stats, CASE_LOWER );
        return ReviewUtils::formatStats( $stats, $this->getChunkReviews() ) ;
    }

    /**
     * @param Chunks_ChunkStruct $jStruct
     * @param FeatureSet         $featureSet
     * @param WordCount_Struct   $jobStats
     * @param                    $chunkReview
     *
     * @return array
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \ReflectionException
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     * @internal param $reviseIssues
     */
    protected function legacyRevisionQualityVars( Chunks_ChunkStruct $jStruct, FeatureSet $featureSet, WordCount_Struct $jobStats, $chunkReview ) {
        $reviseIssues = [];

        $reviseClass = new \Constants_Revise();

        $jobQA = new \Revise_JobQA(
                $jStruct->id,
                $jStruct->password,
                $jobStats->getTotal(),
                $reviseClass
        );

        list( $jobQA, $reviseClass ) = $featureSet->filter( "overrideReviseJobQA", [
                $jobQA, $reviseClass
        ], $jStruct->id,
                $jStruct->password,
                $jobStats->getTotal() );

        /**
         * @var $jobQA \Revise_JobQA
         */
        $jobQA->retrieveJobErrorTotals();
        $jobQA->evalJobVote();
        $qa_data = $jobQA->getQaData();

        foreach ( $qa_data as $issue ) {
            $reviseIssues[ "err_" . str_replace( " ", "_", strtolower( $issue[ 'field' ] ) ) ] = [
                    'allowed' => $issue[ 'allowed' ],
                    'found'   => $issue[ 'found' ],
                    'founds'  => $issue[ 'founds' ],
                    'vote'    => $issue[ 'vote' ]
            ];
        }

        $quality_overall = strtolower( $chunkReview[ 'minText' ] );

        $score = 0;

        $total_issues_weight = 0;
        $total_reviewed_words_count = 0;

        $categories = CatUtils::getSerializedCategories( $reviseClass );
        $passfail = ''  ;

        return array(
                $passfail,
                $reviseIssues, $quality_overall, $score, $total_issues_weight, $total_reviewed_words_count, $categories
        );
    }

    /**
     * @param Chunks_ChunkStruct     $jStruct
     * @param Projects_ProjectStruct $project
     * @param                        $chunkReview
     *
     * @return array
     * @internal param $reviseIssues
     */
    protected function revisionQualityVars( Chunks_ChunkStruct $jStruct, Projects_ProjectStruct $project, $chunkReview ) {
        $reviseIssues = [];

        $qualityReportDao = new QualityReportDao();
        $qa_data          = $qualityReportDao->getReviseIssuesByChunk( $jStruct->id, $jStruct->password, $chunkReview->source_page );
        foreach ( $qa_data as $issue ) {
            if ( !isset( $reviseIssues[ $issue->id_category ] ) ) {
                $reviseIssues[ $issue->id_category ] = [
                        'name'   => $issue->issue_category_label,
                        'founds' => [
                                $issue->issue_severity => 1
                        ]
                ];
            } else {
                if ( !isset( $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ] ) ) {
                    $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ] = 1;
                } else {
                    $reviseIssues[ $issue->id_category ][ 'founds' ][ $issue->issue_severity ]++;
                }
            }
        }

        if ( @$chunkReview->is_pass == null ) {
            $quality_overall = null;
            $is_pass = null;
        } elseif ( !empty( $chunkReview->is_pass ) ) {
            $quality_overall = 'excellent';
            $is_pass = (bool)$chunkReview->is_pass;
        } else {
            $quality_overall = 'fail';
            $is_pass = false;
        }

        $chunkReviewModel = RevisionFactory::initFromProject( $project )
                ->setFeatureSet( $project->getFeatures() )
                ->getChunkReviewModel( $chunkReview );

        $score = number_format( $chunkReviewModel->getScore(), 2, ".", "" );

        $total_issues_weight        = $chunkReviewModel->getPenaltyPoints();
        $total_reviewed_words_count = $chunkReviewModel->getReviewedWordsCount();

        $model      = $project->getLqaModel();
        $categories = $model->getCategoriesAndSeverities();
        $passfail = [ 'type' => $model->pass_type, 'options' => [ 'limit' => $chunkReviewModel->getQALimit() ] ] ;

        return array(
                $passfail,
                $reviseIssues, $quality_overall, $is_pass, $score, $total_issues_weight, $total_reviewed_words_count, $categories
        );
    }

}