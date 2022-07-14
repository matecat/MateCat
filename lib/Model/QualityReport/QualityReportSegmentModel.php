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
use Constants;
use Constants_Revise;
use Constants_TranslationStatus;
use Features\ReviewExtended\Model\QualityReportDao;
use Features\ReviewExtended\ReviewUtils;
use Features\TranslationVersions;
use Features\TranslationVersions\Model\TranslationVersionDao;
use FeatureSet;
use LQA\CategoryDao;
use LQA\CategoryStruct;
use LQA\ChunkReviewDao;
use LQA\EntryCommentDao;
use QualityReport_QualityReportSegmentStruct;
use Revise_ReviseDAO;
use Segments_SegmentDao;
use Matecat\SubFiltering\MateCatFilter;
use ZipArchiveExtended;

class QualityReportSegmentModel {

    protected $_legacySegmentRevisions;
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
     * @throws \Exception
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
        if ( !empty( $options[ 'filter' ] ) && in_array( $options[ 'filter' ] [ 'status' ], Constants_TranslationStatus::$REVISION_STATUSES ) ) {
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
     * @throws \Exception
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
     * @throws \Exception
     */
    public function getSegmentsForQR( array $segment_ids, $isForUI = false ) {
        $segmentsDao = new Segments_SegmentDao;
        $data        = $segmentsDao->getSegmentsForQr( $segment_ids, $this->chunk->id, $this->chunk->password );

        $featureSet = new FeatureSet();

        $featureSet->loadForProject( $this->chunk->getProject() );
        $issue_comments = [];

        if ( $featureSet->hasRevisionFeature() ) {
            $issues = QualityReportDao::getIssuesBySegments( $segment_ids, $this->chunk->id );
            if ( !empty( $issues ) ) {
                $issue_comments = ( new EntryCommentDao() )->fetchCommentsGroupedByIssueIds(
                        array_map( function ( $issue ) {
                            return $issue->issue_id;
                        }, $issues )
                );
            }

        } else {
            $reviseDao          = new Revise_ReviseDAO();
            $segments_revisions = $reviseDao->readBySegments( $segment_ids, $this->chunk->id );
        }

        $commentsDao = new \Comments_CommentDao;
        $comments    = $commentsDao->getThreadsBySegments( $segment_ids, $this->chunk->id );
        $codes       = $featureSet->getCodes();

        $last_revisions = [];

        if ( in_array( TranslationVersions::FEATURE_CODE, $codes ) ) {

            $translationVersionDao = new TranslationVersionDao;
            $last_translations     = $translationVersionDao->getLastRevisionsBySegmentsAndSourcePage(
                    $segment_ids, $this->chunk->id, Constants::SOURCE_PAGE_TRANSLATE
            );

            foreach ( $this->_getChunkReviews() as $chunkReview ) {
                $last_revisions [ $chunkReview->source_page ] = $translationVersionDao->getLastRevisionsBySegmentsAndSourcePage(
                        $segment_ids, $this->chunk->id, $chunkReview->source_page
                );
            }

        } else {
            $last_translations = $this->makeSegmentsVersionsUniform( $segment_ids );
        }

        $segments = [];

        foreach ( $data as $index => $seg ) {

            $dataRefMap = \Segments_SegmentOriginalDataDao::getSegmentDataRefMap( $seg->sid );

            /** @var MateCatFilter $Filter */
            $Filter = MateCatFilter::getInstance( $featureSet, $this->chunk->source, $this->chunk->target, $dataRefMap );

            $seg->dataRefMap = $dataRefMap;

            $this->_commonSegmentAssignments( $seg, $Filter, $featureSet, $this->chunk, $isForUI );
            $this->_assignIssues( $seg, isset( $issues ) ? $issues : [], $issue_comments );
            $this->_assignComments( $seg, $comments );
            $this->_populateLastTranslationAndRevision( $seg, $Filter, $last_translations, $last_revisions, $codes );

            // If the segment is pre-translated (maybe from a previously XLIFF file)

            // If the segment is TRANSLATED
            // 'last_translation' and 'suggestion' from 'translation' and
            // set is_pre_translated to true
            if ( null === $seg->last_translation and $seg->status === \Constants_TranslationStatus::STATUS_TRANSLATED ) {

                if ( $isForUI ) {
                    $seg->last_translation = $Filter->fromLayer0ToLayer2( $seg->translation );
                }

                // this means the job has a bilingual file
                if ( '' === $seg->suggestion ) {
                    if ( $isForUI ) {
                        $seg->suggestion = $Filter->fromLayer0ToLayer2( $seg->translation );
                    }
                }

                $seg->is_pre_translated = true;
            }

            // If the segment was APPROVED
            // check if exists a version 0 'translation' (which means that the segment was modified); if not then use the current 'translation'
            if ( null === $seg->last_translation and $seg->status === \Constants_TranslationStatus::STATUS_APPROVED ) {

                $first_version = ( new TranslationVersionDao() )->getVersionNumberForTranslation( $this->chunk->id, $seg->sid, 0 );
                $translation = ($first_version) ? $first_version->translation : $seg->translation;

                if($isForUI){
                    $seg->last_translation = $Filter->fromLayer0ToLayer2( $translation );
                }

                // this means the job has a bilingual file
                if ( '' === $seg->suggestion ) {
                    if ( $isForUI ) {
                        $seg->suggestion = $Filter->fromLayer0ToLayer2( $translation );
                    }
                }

                if(null === $seg->last_translation){
                    $seg->last_translation = $seg->suggestion;
                }

                $seg->is_pre_translated = true;

                //
                // -------------------------------
                // Note 2020-05-29
                // -------------------------------
                //
                // We check if the segment is not a pre-approved, locked ICE without associated events.
                // In this case we won't to add it twice to last_revisions array
                //
                $revisionCount = ( false === $this->isAnApprovedIce( $seg ) ) ? count( $this->_getChunkReviews() ) : 1;

                for ( $i = 1; $i <= $revisionCount; $i++ ) {
                    $seg->last_revisions [] = [
                            'revision_number' => $i,
                            'translation'     => $translation
                    ];
                }
            }

            $seg->pee_translation_revise     = $seg->getPEEBwtTranslationRevise();
            $seg->pee_translation_suggestion = $seg->getPEEBwtTranslationSuggestion();

            $segments[ $index ] = $seg;
        }

        return $segments;
    }

    /**
     * This function checks if a segment is an approved locked ICE without associated events
     * (in other terms is a pre-approved segment)
     *
     * @param QualityReport_QualityReportSegmentStruct $qrSegmentStruct
     *
     * @return bool
     */
    private function isAnApprovedIce( QualityReport_QualityReportSegmentStruct $qrSegmentStruct ) {
        return (
                $qrSegmentStruct->locked == 1 &&
                $qrSegmentStruct->status === Constants_TranslationStatus::STATUS_APPROVED &&
                $qrSegmentStruct->match_type === 'ICE' &&
                $qrSegmentStruct->source_page == null
        );
    }

    /**
     * @return \LQA\ChunkReviewStruct[]
     */
    protected function _getChunkReviews() {
        if ( is_null( $this->_chunkReviews ) ) {
            $this->_chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk );
        }

        return $this->_chunkReviews;
    }

    protected function _getLegacySegmentRevisions( $segments_id ) {
        if ( $this->_legacySegmentRevisions == null ) {
            $reviseDao                     = new Revise_ReviseDAO();
            $this->_legacySegmentRevisions = $reviseDao->readBySegments( $segments_id, $this->chunk->id );
        }

        return $this->_legacySegmentRevisions;
    }

    private function makeSegmentsVersionsUniform( $segment_ids ) {
        $array = [];
        foreach ( $this->_getLegacySegmentRevisions( $segment_ids ) as $segment_version ) {
            $array[] = new \DataAccess\ShapelessConcreteStruct( [
                    'id_segment'     => $segment_version->id_segment,
                    'translation'    => $segment_version->original_translation,
                    'version_number' => 0,
                    'creation_date'  => null,
                    'source_page'    => 1
            ] );
        }

        return $array;

    }

    private function makeIssuesDataUniform( $issues ) {
        $issues_categories = [];
        foreach ( $issues as $issue ) {
            $issues_categories = array_merge( $issues_categories, $this->makeIssueDataUniform( $issue ) );
        }

        return $issues_categories;
    }

    /**
     * This is a temporary method. It helps to uniformize issues data coming from different features.
     * From the oldest one version to the new one.
     *
     * @param $issue
     *
     * @return \DataAccess\ShapelessConcreteStruct[]
     */

    private function makeIssueDataUniform( $issue ) {

        $categories_values = Constants_Revise::$categoriesDbNames;

        $categories = [];
        foreach ( $categories_values as $category_value ) {

            $issue_old_severity = $issue->{$category_value};
            if ( $issue_old_severity == "" || $issue_old_severity == "none" ) {
                continue;
            }

            $categories[] = new \DataAccess\ShapelessConcreteStruct( [
                    "segment_id"          => $issue->id_segment,
                    "issue_id"            => null,
                    "issue_create_date"   => null,
                    "issue_replies_count" => "0",
                    "issue_start_offset"  => "0",
                    "issue_end_offset"    => "0",
                    "issue_category"      => constant( "Constants_Revise::" . strtoupper( $category_value ) ),
                    "category_options"    => null,
                    "issue_severity"      => $issue_old_severity,
                    "issue_comment"       => null,
                    "target_text"         => null,
                    "issue_uid"           => null,
                    "warning_scope"       => null,
                    "warning_data"        => null,
                    "warning_severity"    => null
            ] );

        }

        return $categories;

    }

    /**
     * @param $last_translations
     * @param $seg
     * @param $Filter
     * @param $last_revisions
     * @param $codes
     *
     * @throws \Exception
     */
    protected function _populateLastTranslationAndRevision( $seg, MateCatFilter $Filter, $last_translations, $last_revisions, $codes ) {
        $last_translation = $this->_findLastTransaltion( $seg, $Filter, $last_translations );

        // last revision version object
        $last_segment_revisions = $this->_findLastRevision( $seg, $Filter, $last_revisions );

        if ( $seg->status == Constants_TranslationStatus::STATUS_TRANSLATED or $seg->status == Constants_TranslationStatus::STATUS_APPROVED ) {
            if ( !empty( $last_translation ) ) {
                $seg->last_translation = $last_translation->translation;
            }

            if ( !empty( $last_segment_revisions ) ) {
                $seg->last_revisions = [];
                foreach ( $last_segment_revisions as $source_page => $revision ) {
                    $seg->last_revisions[] = [
                            'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $source_page ),
                            'translation'     => $revision->translation
                    ];
                }

            }
        }

        if ( !in_array( TranslationVersions::FEATURE_CODE, $codes ) ) {
            if ( $seg->status == Constants_TranslationStatus::STATUS_APPROVED ) {
                $seg->last_revisions[] = [
                        'revision_number' => 1,
                        'translation'     => $seg->translation
                ];

            }
            if ( $seg->status == Constants_TranslationStatus::STATUS_TRANSLATED ) {
                $seg->last_translation = $seg->translation;
            }
        }
    }

    /**
     * @param               $seg
     * @param MateCatFilter $Filter
     * @param               $last_translations
     *
     * @return null
     */
    protected function _findLastTransaltion( $seg, MateCatFilter $Filter, $last_translations ) {
        $find_last_translation_version = null;
        if ( isset( $last_translations ) && !empty( $last_translations ) ) {
            foreach ( $last_translations as $last_translation ) {
                if ( $last_translation->id_segment == $seg->sid ) {
                    $last_translation->translation = $Filter->fromLayer0ToLayer2( $last_translation->translation );
                    $find_last_translation_version = $last_translation;
                    break;
                }
            }
        }

        return $find_last_translation_version;
    }

    /**
     * @param               $seg
     * @param MateCatFilter $Filter
     * @param               $last_revisions
     *
     * @return null
     * @throws \Exception
     */
    protected function _findLastRevision( $seg, MateCatFilter $Filter, $last_revisions ) {
        $segment_last_revisions = [];

        if ( !empty( $last_revisions ) ) {
            foreach ( $last_revisions as $source_page => $source_page_revisions ) {
                foreach ( $source_page_revisions as $last_revision ) {
                    if ( $last_revision->id_segment == $seg->sid ) {
                        $last_revision->translation             = $Filter->fromLayer0ToLayer2( $last_revision->translation );
                        $segment_last_revisions[ $source_page ] = $last_revision;
                        break;
                    }
                }
            }
        }

        return $segment_last_revisions;
    }


}
