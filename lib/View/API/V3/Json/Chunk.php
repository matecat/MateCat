<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 16:16
 */

namespace View\API\V3\Json;

use Constants;
use Exception;
use Exceptions\NotFoundException;
use Features\ReviewExtended\ReviewUtils;
use FeatureSet;
use Jobs_JobDao;
use Jobs_JobStruct;
use Langs\LanguageDomains;
use Langs\Languages;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use Projects_ProjectStruct;
use ReflectionException;
use Utils;
use View\API\App\Json\OutsourceConfirmation;
use View\API\V2\Json\JobTranslator;
use WordCount\WordCountStruct;

class Chunk extends \View\API\V2\Json\Chunk {

    protected array          $chunk_reviews = [];
    protected Jobs_JobStruct $chunk;

    /**
     * @param Jobs_JobStruct $chunk
     *
     * @return array
     * @throws Exception
     * @throws NotFoundException
     */
    public function renderOne( Jobs_JobStruct $chunk ): array {
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
     * @param                         $chunk Jobs_JobStruct
     *
     * @param Projects_ProjectStruct  $project
     * @param FeatureSet              $featureSet
     *
     * @return array
     * @throws Exception
     */
    public function renderItem( Jobs_JobStruct $chunk, Projects_ProjectStruct $project, FeatureSet $featureSet ): array {

        $this->chunk   = $chunk;
        $outsourceInfo = $chunk->getOutsource();
        $tStruct       = $chunk->getTranslator();
        $outsource     = null;
        $translator    = null;
        if ( !empty( $outsourceInfo ) ) {
            $outsource = ( new OutsourceConfirmation( $outsourceInfo ) )->render();
        } else {
            $translator = ( !empty( $tStruct ) ? ( new JobTranslator( $tStruct ) )->renderItem() : null );
        }

        $jobStats = WordCountStruct::loadFromJob( $chunk );

        $lang_handler = Languages::getInstance();

        $subject_handler = LanguageDomains::getInstance();
        $subjectsHashMap = $subject_handler->getEnabledHashMap();

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
                'subject_printable'       => $subjectsHashMap[ $chunk->subject ],
                'owner'                   => $chunk->owner,
                'time_to_edit'            => $this->getTimeToEditArray( $chunk->id ),
                'total_time_to_edit'      => $chunk->total_time_to_edit,
                'avg_post_editing_effort' => (float)$chunk->avg_post_editing_effort,
                'open_threads_count'      => (int)$chunk->getOpenThreadsCount(),
                'created_at'              => Utils::api_timestamp( $chunk->create_date ),
                'pee'                     => $chunk->getPeeForTranslatedSegments(),
                'private_tm_key'          => $this->getKeyList( $chunk ),
                'warnings_count'          => $warningsCount->warnings_count,
                'warning_segments'        => ( $warningsCount->warning_segments ?? [] ),
                'stats'                   => $jobStats,
                'outsource'               => $outsource,
                'translator'              => $translator,
                'total_raw_wc'            => $chunk->total_raw_wc,
                'standard_wc'             => (float)$chunk->standard_analysis_wc,
        ];


        $chunkReviewsList = $this->getChunkReviews();

        $result = array_merge( $result, ( new QualitySummary( $chunk, $project ) )->render( $chunkReviewsList ) );

        foreach ( $chunkReviewsList as $chunkReview ) {
            $result = static::populateRevisePasswords( $chunkReview, $result );
        }

        return $this->fillUrls( $result, $chunk, $project, $featureSet );

    }

    /**
     * @return array
     * @throws ReflectionException
     */
    protected function getChunkReviews(): array {
        if ( empty( $this->chunk_reviews ) ) {
            $this->chunk_reviews = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk );
        }

        return $this->chunk_reviews;
    }

    /**
     * @param ChunkReviewStruct[] $chunk_reviews
     *
     * @return void
     */
    public function setChunkReviews( array $chunk_reviews ): Chunk {
        $this->chunk_reviews = $chunk_reviews;

        return $this;
    }

    /**
     * @param $chunk_id
     *
     * @return array
     * @throws ReflectionException
     */
    protected function getTimeToEditArray( $chunk_id ): array {

        $jobDao   = new Jobs_JobDao();
        $tteT     = (int)$jobDao->getTimeToEdit( $chunk_id, 1 )[ 'tte' ];
        $tteR1    = (int)$jobDao->getTimeToEdit( $chunk_id, 2 )[ 'tte' ];
        $tteR2    = (int)$jobDao->getTimeToEdit( $chunk_id, 3 )[ 'tte' ];
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
        } else {
            $result[ 'revise_passwords' ][] = [
                    'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $chunk_review->source_page ),
                    'password'        => $chunk_review->review_password
            ];
        }

        return $result;

    }

}