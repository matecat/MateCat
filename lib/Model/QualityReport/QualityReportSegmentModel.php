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
use Features\SecondPassReview;
use Features\TranslationVersions;
use FeatureSet;
use LQA\CategoryDao;
use LQA\CategoryStruct;
use LQA\ChunkReviewDao;
use LQA\EntryCommentDao;
use QualityReport_QualityReportSegmentStruct;
use Revise_ReviseDAO;
use Segments_SegmentDao;
use SubFiltering\Filter;
use Translations_TranslationVersionDao;
use ZipArchiveExtended;

class QualityReportSegmentModel {

    protected $_legacySegmentRevisions;
    protected $chunk ;

    protected $_chunkReviews ;

    public function __construct( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk ;
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
    public function getSegmentsIdForQR( $step = 20, $ref_segment, $where = "after", $options = [] ) {
        if ( isset( $options[ 'filter' ][ 'issue_category' ] ) && $options[ 'filter' ][ 'issue_category' ] != 'all' ) {
            $subCategories = ( new CategoryDao() )->findByIdModelAndIdParent(
                    $this->chunk->getProject()->id_qa_model,
                    $options['filter']['issue_category']
            );

            if ( !empty( $subCategories ) > 0 ) {
                $options[ 'filter' ][ 'issue_category' ] = array_map( function( CategoryStruct $subcat ) {
                    return $subcat->id ;
                }, $subCategories );
            }
        }

        /**
         * Validate revision_number param
         */
        if (  in_array( $options['filter'] ['status'], Constants_TranslationStatus::$REVISION_STATUSES ) ) {
            if ( isset( $options['filter']['revision_number'] ) ) {

                $validRevisionNumbers = array_map( function( $chunkReview ) {
                    return ReviewUtils::sourcePageToRevisionNumber( $chunkReview->source_page );
                }, $this->_getChunkReviews() ) ;

                if ( !in_array( (int) $options['filter']['revision_number'], $validRevisionNumbers )  ) {
                    $options['filter']['revision_number'] = 1 ;
                }
            }
        }

        $segmentsDao = new Segments_SegmentDao();
        $segments_id = $segmentsDao->getSegmentsIdForQR(
                $this->chunk, $step, $ref_segment, $where, $options
        );

        return $segments_id;
    }

    protected function _commonSegmentAssignments( QualityReport_QualityReportSegmentStruct $seg, Filter $Filter ) {
        $seg->warnings            = $seg->getLocalWarning();
        $seg->pee                 = $seg->getPEE();
        $seg->ice_modified        = $seg->isICEModified();
        $seg->secs_per_word       = $seg->getSecsPerWord();
        $seg->parsed_time_to_edit = CatUtils::parse_time_to_edit( $seg->time_to_edit );
        $seg->segment             = $Filter->fromLayer0ToLayer2( $seg->segment );
        $seg->translation         = $Filter->fromLayer0ToLayer2( $seg->translation );
        $seg->suggestion          = $Filter->fromLayer0ToLayer2( $seg->suggestion );
    }

    protected function _assignIssues( $seg, $issues, $issue_comments) {
        foreach ( $issues as $issue ) {
            if ( isset( $issue_comments[ $issue->issue_id] ) ) {
                $issue->comments = $issue_comments[ $issue->issue_id ] ;
            }

            if ( $issue->segment_id == $seg->sid ) {
                $seg->issues[] = $issue;
            }
        }
    }

    protected function _assignComments( $seg, $comments ) {
        foreach ( $comments as $comment ) {
            $comment->templateMessage();
            if ( $comment->id_segment == $seg->sid ) {
                $seg->comments[] = $comment;
            }
        }
    }

    public function getSegmentsForQR( $segments_id ) {
        $segmentsDao = new Segments_SegmentDao;
        $data        = $segmentsDao->getSegmentsForQr( $segments_id, $this->chunk->id, $this->chunk->password );

        $featureSet = new FeatureSet();

        $featureSet->loadForProject( $this->chunk->getProject() );
        $issue_comments     = [];

        if ( $featureSet->hasRevisionFeature() ) {
            $issues = QualityReportDao::getIssuesBySegments( $segments_id, $this->chunk->id );
            if ( !empty( $issues )) {
                $issue_comments = ( new EntryCommentDao() )->fetchCommentsGroupedByIssueIds(
                        array_map( function( $issue ) {
                            return $issue->issue_id ;
                        }, $issues )
                ) ;
            }

        } else {
            $reviseDao          = new Revise_ReviseDAO();
            $segments_revisions = $reviseDao->readBySegments( $segments_id, $this->chunk->id );
            $issues             = $this->makeIssuesDataUniform( $segments_revisions );

        }

        $commentsDao  = new \Comments_CommentDao;
        $comments     = $commentsDao->getThreadsBySegments( $segments_id, $this->chunk->id );
        $codes        = $featureSet->getCodes();

        $last_revisions = [] ;

        if ( in_array( TranslationVersions::FEATURE_CODE, $codes ) ) {

            $translationVersionDao = new Translations_TranslationVersionDao;
            $last_translations     = $translationVersionDao->getLastRevieionsBySegmentsAndSourcePage(
                    $segments_id, $this->chunk->id, Constants::SOURCE_PAGE_TRANSLATE
            );

            foreach( $this->_getChunkReviews() as $chunkReview ) {
                $last_revisions [ $chunkReview->source_page ] = $translationVersionDao->getLastRevieionsBySegmentsAndSourcePage(
                        $segments_id, $this->chunk->id, $chunkReview->source_page
                );
            }

        } else {
            $last_translations = $this->makeSegmentsVersionsUniform( $segments_id );
        }

        $Filter = Filter::getInstance( $featureSet );

        $files = [];

        foreach ( $data as $i => $seg ) {

            if ( ! isset( $files [ $seg->id_file ] ) ) {
                $files [ $seg->id_file ][ "filename" ] = ZipArchiveExtended::getFileName($seg->filename);
                $files [ $seg->id_file ][ "segments" ] = [];
            }

            $this->_commonSegmentAssignments( $seg, $Filter ) ;
            $this->_assignIssues( $seg, $issues, $issue_comments ) ;
            $this->_assignComments( $seg, $comments ) ;
            $this->_populateLastTranslationAndRevision( $seg, $Filter, $last_translations, $last_revisions, $codes );

            $seg->pee_translation_revise     = $seg->getPEEBwtTranslationRevise();
            $seg->pee_translation_suggestion = $seg->getPEEBwtTranslationSuggestion();

            $files[$seg->id_file]['segments'][] = $seg;
        }

        return $files;
    }

    /**
     * @return \LQA\ChunkReviewStruct[]
     */
    protected function _getChunkReviews() {
        if ( is_null( $this->_chunkReviews ) ) {
            $this->_chunkReviews = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk );
        }
        return $this->_chunkReviews ;
    }

    protected function _getLegacySegmentRevisions($segments_id) {
       if ( $this->_legacySegmentRevisions == null ) {
           $reviseDao          = new Revise_ReviseDAO();
           $this->_legacySegmentRevisions = $reviseDao->readBySegments( $segments_id, $this->chunk->id );
       }
       return $this->_legacySegmentRevisions ;
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
     */
    protected function _populateLastTranslationAndRevision( $seg, Filter $Filter, $last_translations, $last_revisions, $codes ) {
        $last_translation = $this->_findLastTransaltion( $seg, $Filter, $last_translations );

        // last revision version object
        $last_segment_revisions = $this->_findLastRevision( $seg, $Filter, $last_revisions );

        if ( $seg->status == Constants_TranslationStatus::STATUS_TRANSLATED OR $seg->status == Constants_TranslationStatus::STATUS_APPROVED ) {
            if ( !empty( $last_translation ) ) {
                $seg->last_translation = $last_translation->translation;
            }

            if ( !empty( $last_segment_revisions ) ) {
                $seg->last_revisions = [] ;
                foreach( $last_segment_revisions as $source_page => $revision ) {
                    $seg->last_revisions[] = [
                            'revision_number' => ReviewUtils::sourcePageToRevisionNumber( $source_page ) ,
                            'translation'     => $revision->translation
                    ];
                }

            }
        }

        if ( !in_array( TranslationVersions::FEATURE_CODE, $codes ) ) {
            if ( $seg->status == Constants_TranslationStatus::STATUS_APPROVED ) {
                $seg->last_revisions[] = [
                    'revision_number' => 1,
                    'translation' => $seg->translation
                ] ;

            }
            if ( $seg->status == Constants_TranslationStatus::STATUS_TRANSLATED ) {
                $seg->last_translation = $seg->translation;
            }
        }
    }

    /**
     * @param        $seg
     * @param Filter $Filter
     * @param        $last_translations
     *
     * @return null
     */
    protected function _findLastTransaltion( $seg, Filter $Filter, $last_translations ) {
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
     * @param        $seg
     * @param Filter $Filter
     * @param        $last_revisions
     *
     * @return null
     */
    protected function _findLastRevision( $seg, Filter $Filter, $last_revisions ) {
        $segment_last_revisions = [] ;

        if ( !empty( $last_revisions ) ) {
            foreach ( $last_revisions as $source_page => $source_page_revisions ) {
                foreach ( $source_page_revisions as $last_revision ) {
                    if ( $last_revision->id_segment == $seg->sid ) {
                        $last_revision->translation = $Filter->fromLayer0ToLayer2( $last_revision->translation );
                        $segment_last_revisions[ $source_page ] = $last_revision;
                        break;
                    }
                }
            }
        }

        return $segment_last_revisions;
    }


}
