<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 07/09/2018
 * Time: 11:21
 */

namespace QualityReport;

use CatUtils;
use Chunks_ChunkStruct;
use Comments_CommentDao;
use Constants;
use Constants_TranslationStatus;
use Exception;
use Features\ReviewExtended\Model\QualityReportDao;
use Features\ReviewExtended\ReviewUtils;
use Features\TranslationVersions\Model\TranslationVersionDao;
use FeatureSet;
use LQA\CategoryDao;
use LQA\CategoryStruct;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use LQA\EntryCommentDao;
use Matecat\SubFiltering\MateCatFilter;
use QualityReport_QualityReportSegmentStruct;
use Segments_SegmentDao;
use Segments_SegmentOriginalDataDao;

class QualityReportSegmentModel {

    protected $chunk;

    protected $_chunkReviews;

    public function __construct( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk;
    }

    /**
     * @param int    $step
     * @param        $ref_segment
     * @param string $where
     * @param array  $options
     *
     * @return array
     * @throws Exception
     */
    public function getSegmentsIdForQR( $step, $ref_segment, $where = "after", $options = [] ) {
        if ( isset( $options[ 'filter' ][ 'issue_category' ] ) && $options[ 'filter' ][ 'issue_category' ] != 'all' ) {
            $subCategories = ( new CategoryDao() )->findByIdModelAndIdParent(
                    $this->chunk->getProject()->id_qa_model,
                    $options[ 'filter' ][ 'issue_category' ]
            );

            if ( !empty( $subCategories ) > 0 ) {
                $options[ 'filter' ][ 'issue_category' ] = array_map( function ( CategoryStruct $subcat ) {
                    return $subcat->id;
                }, $subCategories );
            }
        }

        /**
         * Validate revision_number param
         */
        if ( !empty( $options[ 'filter' ] ) && in_array( ( $options[ 'filter' ] [ 'status' ] ?? '' ), Constants_TranslationStatus::$REVISION_STATUSES ) ) {
            if ( isset( $options[ 'filter' ][ 'revision_number' ] ) ) {

                $validRevisionNumbers = array_map( function ( $chunkReview ) {
                    return ReviewUtils::sourcePageToRevisionNumber( $chunkReview->source_page );
                }, $this->_getChunkReviews() );

                if ( !in_array( (int)$options[ 'filter' ][ 'revision_number' ], $validRevisionNumbers ) ) {
                    $options[ 'filter' ][ 'revision_number' ] = 1;
                }
            }
        }

        $segmentsDao = new Segments_SegmentDao();
        $segments_id = $segmentsDao->getSegmentsIdForQR(
                $this->chunk, $step, $ref_segment, $where, $options
        );

        return $segments_id;
    }

    /**
     * @param QualityReport_QualityReportSegmentStruct $seg
     * @param MateCatFilter                            $Filter
     * @param FeatureSet                               $featureSet
     * @param Chunks_ChunkStruct                       $chunk
     * @param bool                                     $isForUI
     *
     * @throws Exception
     */
    protected function _commonSegmentAssignments( QualityReport_QualityReportSegmentStruct $seg, MateCatFilter $Filter, FeatureSet $featureSet, Chunks_ChunkStruct $chunk, $isForUI = false ) {
        $seg->warnings            = $seg->getLocalWarning( $featureSet, $chunk );
        $seg->pee                 = $seg->getPEE();
        $seg->ice_modified        = $seg->isICEModified();
        $seg->secs_per_word       = $seg->getSecsPerWord();
        $seg->parsed_time_to_edit = CatUtils::parse_time_to_edit( $seg->time_to_edit );

        if ( $isForUI ) {
            $seg->segment     = $Filter->fromLayer0ToLayer2( $seg->segment );
            $seg->translation = $Filter->fromLayer0ToLayer2( $seg->translation );
            $seg->suggestion  = $Filter->fromLayer0ToLayer2( $seg->suggestion );
        }
    }

    /**
     * @param $seg
     * @param $issues
     * @param $issue_comments
     */
    protected function _assignIssues( $seg, $issues, $issue_comments ) {
        foreach ( $issues as $issue ) {

            $issue->revision_number = ReviewUtils::sourcePageToRevisionNumber( $issue->source_page );

            if ( isset( $issue_comments[ $issue->issue_id ] ) ) {
                $issue->comments = $issue_comments[ $issue->issue_id ];
            }

            if ( $issue->segment_id == $seg->sid ) {
                $seg->issues[] = $issue;
            }
        }
    }

    /**
     * @param $seg
     * @param $comments
     */
    protected function _assignComments( $seg, $comments ) {
        foreach ( $comments as $comment ) {
            $comment->templateMessage();
            if ( $comment->id_segment == $seg->sid ) {
                $seg->comments[] = $comment;
            }
        }
    }

    /**
     * If the results are needed from UI return a layer2 presentation,
     * otherwise return just plain text (layer 0)
     *
     * @param array $segment_ids
     * @param bool  $isForUI
     *
     * @return array
     * @throws Exception
     */
    public function getSegmentsForQR( array $segment_ids, $isForUI = false ) {
        $segmentsDao = new Segments_SegmentDao;
        $data        = $segmentsDao->getSegmentsForQr( $segment_ids, $this->chunk->id, $this->chunk->password );

        $featureSet = new FeatureSet();

        $featureSet->loadForProject( $this->chunk->getProject() );
        $issue_comments = [];

        $issues = QualityReportDao::getIssuesBySegments( $segment_ids, $this->chunk->id );
        if ( !empty( $issues ) ) {
            $issue_comments = ( new EntryCommentDao() )->fetchCommentsGroupedByIssueIds(
                    array_map( function ( $issue ) {
                        return $issue->issue_id;
                    }, $issues )
            );
        }

        $commentsDao = new Comments_CommentDao;
        $comments    = $commentsDao->getThreadsBySegments( $segment_ids, $this->chunk->id );

        $all_events = [];

        $translationVersionDao = new TranslationVersionDao;
//        $last_translations     = $translationVersionDao->getLastRevisionsBySegmentsAndSourcePage(
//                $segment_ids, $this->chunk->id, Constants::SOURCE_PAGE_TRANSLATE
//        );

//        foreach ( $this->_getChunkReviews() as $chunkReview ) {
//            $last_revisions [ $chunkReview->source_page ] = $revs = $translationVersionDao->getLastRevisionsBySegmentsAndSourcePage(
//                    $segment_ids, $this->chunk->id, $chunkReview->source_page
//            );
////            $all_versions_flattened                       = array_merge( $all_versions_flattened, $revs );
//        }
//        $all_versions_flattened = array_merge( $last_translations, $all_versions_flattened );
        $all_events = $translationVersionDao->getAllRelevantEvents( $segment_ids, $this->chunk->id );

        $segments = [];

        foreach ( $data as $index => $seg ) {

            $dataRefMap = Segments_SegmentOriginalDataDao::getSegmentDataRefMap( $seg->sid );

            /** @var MateCatFilter $Filter */
            $Filter = MateCatFilter::getInstance( $featureSet, $this->chunk->source, $this->chunk->target, $dataRefMap );

            $seg->dataRefMap = $dataRefMap;

            $this->_commonSegmentAssignments( $seg, $Filter, $featureSet, $this->chunk, $isForUI );
            $this->_assignIssues( $seg, $issues ?? [], $issue_comments );
            $this->_assignComments( $seg, $comments );
            $this->_populateLastTranslationAndRevision( $seg, $Filter, $all_events, $isForUI );

            $seg->pee_translation_revise     = $seg->getPEEBwtTranslationRevise();
            $seg->pee_translation_suggestion = $seg->getPEEBwtTranslationSuggestion();

            $segments[ $index ] = $seg;
        }

        return $segments;
    }

    /**
     * @return ChunkReviewStruct[]
     */
    protected function _getChunkReviews() {
        if ( is_null( $this->_chunkReviews ) ) {
            $this->_chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk );
        }

        return $this->_chunkReviews;
    }

    /**
     * @param QualityReport_QualityReportSegmentStruct $seg
     * @param MateCatFilter                            $Filter
     * @param SegmentEventsStruct[]                    $events
     * @param bool                                     $isForUI
     *
     * @throws Exception
     */
    protected function _populateLastTranslationAndRevision(
            QualityReport_QualityReportSegmentStruct $seg,
            MateCatFilter                            $Filter,
            array                                    $events,
            bool                                     $isForUI = false
    ): void {

        // If the segment is pre-translated (maybe from a previously XLIFF file) and NOT modified
        if (
                $seg->getTmAnalysisStatus() == 'SKIPPED' &&
                !$this->isSegmentEventInArray( $seg->sid, $events )
        ) {

            $seg->last_revisions    = [];
            $seg->is_pre_translated = true;
            switch ( $seg->status ) {
                case Constants_TranslationStatus::STATUS_APPROVED:
                    $seg->last_revisions[] = [
                            'revision_number' => 1,
                            'translation'     => ( $isForUI ) ? $Filter->fromLayer0ToLayer2( $seg->translation ) : $seg->translation
                    ];
                    break;
                case Constants_TranslationStatus::STATUS_APPROVED2:
                    $seg->last_revisions[] = [
                            'revision_number' => 2,
                            'translation'     => ( $isForUI ) ? $Filter->fromLayer0ToLayer2( $seg->translation ) : $seg->translation
                    ];
                    break;
                case Constants_TranslationStatus::STATUS_TRANSLATED:
                    $seg->last_translation = ( $isForUI ) ? $Filter->fromLayer0ToLayer2( $seg->translation ) : $seg->translation;
                    break;
                default:
                    $seg->is_pre_translated = false; // unreachable condition
                    break;
            }

        } elseif ( Constants_TranslationStatus::isNotInitialStatus( $seg->status ) ) {

            foreach ( $events as $event ) {

                if ( $seg->sid != $event->id_segment ) {
                    continue;
                }

                $translation = ( $isForUI ) ? $Filter->fromLayer0ToLayer2( $event->translation ) : $event->translation;

                if ( $event->source_page == Constants::SOURCE_PAGE_TRANSLATE ) {
                    $seg->last_translation = $translation;
                } else {
                    $seg->last_revisions[] = [
                            'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $event->source_page ),
                            'translation'     => $translation
                    ];
                }

            }

        }

    }

    /**
     * @param SegmentEventsStruct[] $haystack_events
     * @param int                   $needle_segment_id
     *
     * @return bool
     */
    protected function isSegmentEventInArray( int $needle_segment_id, array $haystack_events ): bool {
        foreach ( $haystack_events as $event ) {
            if ( $event->id_segment == $needle_segment_id ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int                   $needle_segment_id
     * @param int                   $needle_source_page
     * @param SegmentEventsStruct[] $haystack_events
     *
     * @return mixed|null
     */
    protected function filterEvent( int $needle_segment_id, int $needle_source_page, array $haystack_events ) {
        foreach ( $haystack_events as $event ) {
            if ( $event->id_segment == $needle_segment_id && $event->source_page == $needle_source_page ) {
                return $event;
            }
        }

        return null;
    }

}
