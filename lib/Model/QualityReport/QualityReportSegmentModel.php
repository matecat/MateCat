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
use Constants_Revise;
use Constants_TranslationStatus;
use Features\ReviewExtended;
use Features\ReviewImproved;
use Features\ReviewImproved\Model\QualityReportDao;
use Features\TranslationVersions;
use FeatureSet;
use Translations_TranslationVersionDao;
use ZipArchiveExtended;

class QualityReportSegmentModel {

    public function getSegmentsIdForQR( Chunks_ChunkStruct $chunk, $step = 10, $ref_segment, $where = "after", $options = [] ) {

        $segmentsDao = new \Segments_SegmentDao;

        $segments_id = $segmentsDao->getSegmentsIdForQR( $chunk->id, $chunk->password, $step, $ref_segment, $where, $options );

        return $segments_id;
    }

    public function getSegmentsForQR( $segments_id, Chunks_ChunkStruct $chunk ) {
        $segmentsDao = new \Segments_SegmentDao;
        $data        = $segmentsDao->getSegmentsForQr( $segments_id, $chunk->id, $chunk->password );

        $featureSet = new FeatureSet();

        $featureSet->loadForProject( $chunk->getProject() );

        $codes = $featureSet->getCodes();
        if ( in_array( ReviewExtended::FEATURE_CODE, $codes ) OR in_array( ReviewImproved::FEATURE_CODE, $codes ) ) {
            $issues = QualityReportDao::getIssuesBySegments( $segments_id, $chunk->id );
        } else {
            $reviseDao          = new \Revise_ReviseDAO();
            $segments_revisions = $reviseDao->readBySegments( $segments_id, $chunk->id );
            $issues             = $this->makeIssuesDataUniform( $segments_revisions );
        }

        $commentsDao = new \Comments_CommentDao;
        $comments    = $commentsDao->getThreadsBySegments( $segments_id, $chunk->id );


        if ( in_array( TranslationVersions::FEATURE_CODE, $codes ) ) {
            $translationVersionDao = new Translations_TranslationVersionDao;
            $last_translations     = $translationVersionDao->getLastTranslationsBySegments( $segments_id, $chunk->id );
            $last_revisions        = $translationVersionDao->getLastRevisionsBySegments( $segments_id, $chunk->id );
        } else {
            if ( !isset( $segments_revisions ) ) {
                $reviseDao          = new \Revise_ReviseDAO();
                $segments_revisions = $reviseDao->readBySegments( $segments_id, $chunk->id );
            }

            $last_translations = $this->makeSegmentsVersionsUniform( $segments_revisions );
        }


        $files = [];
        foreach ( $data as $i => $seg ) {

            $id_file = $seg->id_file;

            if ( !isset($files[$id_file]) ) {
                $files[$id_file]["filename"] = ZipArchiveExtended::getFileName($seg->filename);
                $files[$id_file]['segments'] = [];
            }

            $seg->warnings      = $seg->getLocalWarning();
            $seg->pee           = $seg->getPEE();
            $seg->ice_modified  = $seg->isICEModified();
            $seg->secs_per_word = $seg->getSecsPerWord();

            $seg->parsed_time_to_edit = CatUtils::parse_time_to_edit( $seg->time_to_edit );

            $seg->segment = CatUtils::rawxliff2view( $seg->segment );

            $seg->translation = CatUtils::rawxliff2view( $seg->translation );
            $seg->suggestion = CatUtils::rawxliff2view( $seg->suggestion );

            foreach ( $issues as $issue ) {
                if ( $issue->segment_id == $seg->sid ) {
                    $seg->issues[] = $issue;
                }
            }

            foreach ( $comments as $comment ) {
                $comment->templateMessage();
                if ( $comment->id_segment == $seg->sid ) {
                    $seg->comments[] = $comment;
                }
            }

            $find_last_translation_version = null;
            if(isset($last_translations) && !empty($last_translations)){
                foreach ( $last_translations as $last_translation ) {
                    if ( $last_translation->id_segment == $seg->sid ) {
                        $last_translation->translation = CatUtils::rawxliff2view( $last_translation->translation );
                        $find_last_translation_version = $last_translation;
                        break;
                    }
                }
            }

            //last revision version object
            $find_last_revision_version = null;
            if ( isset( $last_revisions ) && !empty($last_revisions) ) {
                foreach ( $last_revisions as $last_revision ) {
                    if ( $last_revision->id_segment == $seg->sid ) {
                        $last_revision->translation = CatUtils::rawxliff2view( $last_revision->translation );
                        $find_last_revision_version = $last_revision;
                        break;
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

            if (! in_array( TranslationVersions::FEATURE_CODE, $codes ) ) {
                if($seg->status == Constants_TranslationStatus::STATUS_APPROVED){
                    $seg->last_revision = $seg->translation;
                }
                if($seg->status == Constants_TranslationStatus::STATUS_TRANSLATED){
                    $seg->last_translation = $seg->translation;
                }
            }



            $seg->pee_translation_revise     = $seg->getPEEBwtTranslationRevise();
            $seg->pee_translation_suggestion = $seg->getPEEBwtTranslationSuggestion();

            $files[$seg->id_file]['segments'][] = $seg;
        }

        return $files;

    }

    private function makeSegmentsVersionsUniform( $segments_versions ) {
        $array = [];
        foreach ( $segments_versions as $segment_version ) {
            $array[] = new \DataAccess\ShapelessConcreteStruct( [
                    'id_segment'     => $segment_version->id_segment,
                    'translation'    => $segment_version->original_translation,
                    'version_number' => 0,
                    'creation_date'  => null,
                    'is_review'      => 0
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


}
