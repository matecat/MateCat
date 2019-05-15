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
use Features\ReviewExtended\Model\QualityReportModel;
use Features\TranslationVersions;
use FeatureSet;
use LQA\CategoryDao;
use LQA\CategoryStruct;
use LQA\ChunkReviewDao;
use QualityReport_QualityReportSegmentStruct;
use Revise_ReviseDAO;
use Segments_SegmentDao;
use SubFiltering\Filter;
use Translations_TranslationVersionDao;
use ZipArchiveExtended;

class QualityReportSegmentModel {

    protected $_legacySegmentRevisions;
    protected $chunk ;

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
     */
    public function getSegmentsIdForQR( $step = 10, $ref_segment, $where = "after", $options = [] ) {

        if ( isset( $options[ 'filter' ][ 'issue_category' ] ) ) {
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

        $segmentsDao = new Segments_SegmentDao();
        $segments_id = $segmentsDao->getSegmentsIdForQR(
                $this->chunk->id, $this->chunk->password,
                $step, $ref_segment, $where, $options
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

    protected function _assignIssues( $seg, $issues ) {
        foreach ( $issues as $issue ) {
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


        if ( $featureSet->hasRevisionFeature() ) {

            $issues = QualityReportDao::getIssuesBySegments( $segments_id, $this->chunk->id );

        } else {

            $reviseDao          = new Revise_ReviseDAO();
            $segments_revisions = $reviseDao->readBySegments( $segments_id, $this->chunk->id );
            $issues             = $this->makeIssuesDataUniform( $segments_revisions );

        }

        $commentsDao  = new \Comments_CommentDao;
        $comments     = $commentsDao->getThreadsBySegments( $segments_id, $this->chunk->id );
        $codes        = $featureSet->getCodes();
        $chunkReviews = ChunkReviewDao::findChunkReviewsByChunkIds( [ [ $this->chunk->id, $this->chunk->password ] ] ) ;
        $last_revisions = [] ;

        if ( in_array( TranslationVersions::FEATURE_CODE, $codes ) ) {

            $translationVersionDao = new Translations_TranslationVersionDao;
            $last_translations     = $translationVersionDao->getLastRevieionsBySegmentsAndSourcePage(
                    $segments_id, $this->chunk->id, Constants::SOURCE_PAGE_TRANSLATE
            );

            foreach( $chunkReviews as $chunkReview ) {
                $last_revisions [ $chunkReview->source_page ] = $translationVersionDao->getLastRevieionsBySegmentsAndSourcePage(
                        $segments_id, $this->chunk->id, $chunkReview->source_page
                );
            }

        } else {

            if ( !isset( $segments_revisions ) ) {
            }

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
            $this->_assignIssues( $seg, $issues ) ;
            $this->_assignComments( $seg, $comments ) ;
            $this->_populateLastTranslationAndRevision( $seg, $Filter, $last_translations, $last_revisions, $codes );

            $seg->pee_translation_revise     = $seg->getPEEBwtTranslationRevise();
            $seg->pee_translation_suggestion = $seg->getPEEBwtTranslationSuggestion();

            $files[$seg->id_file]['segments'][] = $seg;
        }

        return $files;
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

        // last revision version object
        $find_last_revision_version = null;
        if ( !empty( $last_revisions ) ) {
            foreach ( $last_revisions as $source_page => $source_page_revisions ) {
                foreach ( $source_page_revisions as $last_revision ) {
                    if ( $last_revision->id_segment == $seg->sid ) {
                        $last_revision->translation = $Filter->fromLayer0ToLayer2( $last_revision->translation );
                        $find_last_revision_version = $last_revision;
                        break;
                    }
                }
            }
        }

        if ( $seg->status == Constants_TranslationStatus::STATUS_TRANSLATED OR $seg->status == Constants_TranslationStatus::STATUS_APPROVED ) {
            if ( !empty( $find_last_translation_version ) ) {
                $seg->last_translation = $find_last_translation_version->translation;
            }
            if ( !empty( $find_last_revision_version ) ) {
                $seg->last_revision = $find_last_revision_version->translation;
            }
        }

        if ( !in_array( TranslationVersions::FEATURE_CODE, $codes ) ) {
            if ( $seg->status == Constants_TranslationStatus::STATUS_APPROVED ) {
                $seg->last_revision = $seg->translation;
            }
            if ( $seg->status == Constants_TranslationStatus::STATUS_TRANSLATED ) {
                $seg->last_translation = $seg->translation;
            }
        }
    }


}
