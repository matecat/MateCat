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
use Features\QaCheckBlacklist\Utils\BlacklistUtils;
use Features\ReviewExtended\ReviewUtils;
use FeatureSet;
use Glossary\Blacklist\BlacklistDao;
use Langs_LanguageDomains;
use Langs_Languages;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use Projects_ProjectStruct;
use RedisHandler;
use Utils;
use WordCount_Struct;

class Chunk extends \API\V2\Json\Chunk {

    protected $chunk_reviews;
    protected $chunk;

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return array
     * @throws \Exception
     * @throws \Exceptions\NotFoundException
     */
    public function renderOne( Chunks_ChunkStruct $chunk ) {
        $project    = $chunk->getProject();
        $featureSet = $project->getFeaturesSet();

        return [
                'job' => [
                        'id'     => (int)$chunk->id,
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

        $this->chunk   = $chunk;
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

        // blacklistWordsCount
        $blacklistWordsCount = null;

        $dao = new BlacklistDao();
        $dao->destroyGetByJobIdAndPasswordCache($chunk->id, $chunk->password);
        $model = $dao->getByJobIdAndPassword($chunk->id, $chunk->password);

        if(!empty($model)){
            $blacklistUtils = new BlacklistUtils( ( new RedisHandler() )->getConnection() );
            $abstractBlacklist = $blacklistUtils->getAbstractBlacklist($chunk);
            $blacklistWordsCount = $abstractBlacklist->getWordsCount();
        }

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
                'time_to_edit'            => $this->getTimeToEditArray( $chunk->id ),
                'total_time_to_edit'      => (int)$chunk->total_time_to_edit,
                'avg_post_editing_effort' => (float)$chunk->avg_post_editing_effort,
                'open_threads_count'      => (int)$chunk->getOpenThreadsCount(),
                'created_at'              => Utils::api_timestamp( $chunk->create_date ),
                'pee'                     => $chunk->getPeeForTranslatedSegments(),
                'private_tm_key'          => $this->getKeyList( $chunk ),
                'warnings_count'          => $warningsCount->warnings_count,
                'warning_segments'        => ( isset( $warningsCount->warning_segments ) ? $warningsCount->warning_segments : [] ),
                'stats'                   => $this->_getStats( $jobStats ),
                'outsource'               => $outsource,
                'translator'              => $translator,
                'total_raw_wc'            => (int)$chunk->total_raw_wc,
                'standard_wc'             => (float)$chunk->standard_analysis_wc,
                'blacklist_word_count'    => $blacklistWordsCount,
        ];

        if ( $featureSet->hasRevisionFeature() ) {

            $chunkReviewsList = $this->getChunkReviews();

            $result = array_merge( $result, ( new QualitySummary( $chunk, $project ) )->render( $chunkReviewsList ) );

            foreach ( $chunkReviewsList as $index => $chunkReview ) {
                $result = static::populateRevisePasswords( $chunkReview, $result );
            }

        } else { //OLD
            $qualityInfoArray = CatUtils::getQualityInfoFromJobStruct( $chunk, $featureSet );

            list( $passfail, $reviseIssues, $quality_overall, $score, $total_issues_weight, $total_reviewed_words_count, $categories ) =
                    $this->legacyRevisionQualityVars( $chunk, $featureSet, $jobStats, $qualityInfoArray );

            $result[ 'quality_summary' ][] = QualitySummary::populateQualitySummarySection(
                    Constants::SOURCE_PAGE_REVISION,
                    $chunk,
                    $quality_overall,
                    $reviseIssues,
                    $score,
                    $categories,
                    $total_issues_weight,
                    $total_reviewed_words_count,
                    $passfail,
                    0,
                    0,
                    0
            );
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
            $this->chunk_reviews = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk );
        }

        return $this->chunk_reviews;
    }

    /**
     * @param $chunk_id
     *
     * @return array
     */
    protected function getTimeToEditArray( $chunk_id ) {

        $jobDao   = new \Jobs_JobDao();
        $tteT     = (int)$jobDao->getTimeToEdit( $chunk_id, 1 )['tte'];
        $tteR1    = (int)$jobDao->getTimeToEdit( $chunk_id, 2 )['tte'];
        $tteR2    = (int)$jobDao->getTimeToEdit( $chunk_id, 3 )['tte'];
        $tteTotal = $tteT + $tteR1 + $tteR2;

        return [
                'total' => $tteTotal,
                't'     => $tteT,
                'r1'    => $tteR1,
                'r2'    => $tteR2,
        ];
    }

    /**
     * @param ChunkReviewStruct $chunk_review
     * @param                   $result
     *
     * @return mixed
     */
    protected static function populateRevisePasswords( ChunkReviewStruct $chunk_review, $result ) {

        if ( !isset( $result[ 'revise_passwords' ] ) ) {
            $result[ 'revise_passwords' ] = [];
        }

        if ( $chunk_review->source_page <= Constants::SOURCE_PAGE_REVISION ) {
            $result[ 'revise_passwords' ][] = [
                    'revision_number' => 1,
                    'password'        => $chunk_review->review_password
            ];
        } elseif ( $chunk_review->source_page > Constants::SOURCE_PAGE_REVISION ) {
            $result[ 'revise_passwords' ][] = [
                    'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $chunk_review->source_page ),
                    'password'        => $chunk_review->review_password
            ];
        }

        return $result;

    }

    protected function _getStats( $jobStats ) {
        $stats = CatUtils::getPlainStatsForJobs( $jobStats );
        unset( $stats [ 'id' ] );
        $stats = array_change_key_case( $stats, CASE_LOWER );

        return ReviewUtils::formatStats( $stats, $this->getChunkReviews() );
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

        $total_issues_weight        = 0;
        $total_reviewed_words_count = 0;

        $categories = CatUtils::getSerializedCategories( $reviseClass );
        $passfail   = '';

        return [
                $passfail,
                $reviseIssues, $quality_overall, $score, $total_issues_weight, $total_reviewed_words_count, $categories
        ];
    }

}