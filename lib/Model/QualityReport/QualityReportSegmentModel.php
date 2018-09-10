<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 07/09/2018
 * Time: 11:21
 */

class QualityReport_QualityReportSegmentModel {

    protected $qa_categories;

    public function getSegmentsIdForQR( Chunks_ChunkStruct $chunk, $step = 10, $ref_segment, $where = "after", $options = [] ) {

        $project = $chunk->getProject();

        $model = $project->getLqaModel();
        //If project doesn't have the model, I take model with id 1
        if ( empty( $model ) ) {
            $model = \LQA\ModelDao::findById( 1 );
        }
        $this->qa_categories = \LQA\CategoryDao::getCategoriesByModel( $model );

        $segmentsDao = new \Segments_SegmentDao;
        $segments_id = $segmentsDao->getSegmentsIdForQR( $chunk->id, $chunk->password, $step, $ref_segment, $where );

        return $segments_id;
    }

    public function getSegmentsForQR( $segments_id, $features ) {
        $segmentsDao = new \Segments_SegmentDao;
        $data        = $segmentsDao->getSegmentsForQr( $segments_id );

        $codes = $features->getCodes();
        if ( in_array( Features\ReviewExtended::FEATURE_CODE, $codes ) OR in_array( Features\ReviewImproved::FEATURE_CODE, $codes ) ) {
            $issues = [];
            $issues = \Features\ReviewImproved\Model\QualityReportDao::getIssuesBySegments( $segments_id );
        } else {
            $reviseDao  = new \Revise_ReviseDAO();
            $old_issues = $reviseDao->readBySegments( $segments_id );
            $issues     = $this->makeIssuesDataUniform( $old_issues );
        }

        $commentsDao = new \Comments_CommentDao;
        $comments    = $commentsDao->getThreadsBySegments( $segments_id );

        $translationVersionDao = new Translations_TranslationVersionDao;
        $last_translations     = $translationVersionDao->getLastTranslationsBySegments( $segments_id );
        $last_revisions        = $translationVersionDao->getLastRevisionsBySegments( $segments_id );

        $segments = [];
        foreach ( $data as $i => $seg ) {

            $seg->warnings      = $seg->getLocalWarning();
            $seg->pee           = $seg->getPEE();
            $seg->ice_modified  = $seg->isICEModified();
            $seg->secs_per_word = $seg->getSecsPerWord();

            $seg->parsed_time_to_edit = CatUtils::parse_time_to_edit( $seg->time_to_edit );

            $seg->segment = CatUtils::rawxliff2view( $seg->segment );

            $seg->translation = CatUtils::rawxliff2view( $seg->translation );

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

            if ( $seg->status == Constants_TranslationStatus::STATUS_TRANSLATED ) {
                $seg->last_translation = $seg->translation;
            } else {
                foreach ( $last_translations as $last_translation ) {
                    if ( $last_translation->id_segment == $seg->sid ) {
                        $seg->last_translation = $last_translation->translation;
                    }

                }
            }

            if ( $seg->status == Constants_TranslationStatus::STATUS_APPROVED ) {
                $seg->last_revision = $seg->translation;
            } else {
                foreach ( $last_revisions as $last_revision ) {
                    if ( $last_revision->id_segment == $seg->sid ) {
                        $seg->last_revision = $last_revision->translation;
                    }

                }
            }

            $seg->pee_translation_revise     = $seg->getPEEBwtTranslationRevise();
            $seg->pee_translation_suggestion = $seg->getPEEBwtTranslationSuggestion();

            $segments[] = $seg;
        }

        return $segments;

    }

    public function makeIssuesDataUniform( $issues ) {
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

    public function makeIssueDataUniform( $issue ) {

        $old_categories = [ 'err_typing', 'err_translation', 'err_terminology', 'err_language', 'err_style' ];

        $new_categories = [];
        //here I'm assuming that the order of categories from database is the same of array
        foreach ( $this->qa_categories as $key => $qa_category ) {
            $qa_category->severities = (!is_array($qa_category->severities))?json_decode( $qa_category->severities ):$qa_category->severities;
            $new_categories[ $old_categories[ $key ] ] = $qa_category;
        }

        $categories = [];
        //i'm going to create an array row for each category
        foreach ( $old_categories as $old_category ) {

            $issue_old_severity = $issue->{$old_category};
            if ( $issue_old_severity == "" ) {
                continue;
            }

            $new_category = $new_categories[ $old_category ];

            //this strange name because it's possible that severities values are not the same
            $issue_semi_new_severity  = Constants_Revise::$const2clientValues[ $issue_old_severity ];

            $found_severity           = false;
            foreach ( $new_category->severities as $key => $severity ) {
                if ( $severity->penalty == $issue_semi_new_severity ) {
                    $found_severity = $severity;
                }
            }

            //if I didn't find the severity value in the array, I will put the last value on the previous foreach (I'm assuming is the highest value)
            if ( !$found_severity ) {
                $found_severity = $severity;
            }

            $categories[] = new \DataAccess\ShapelessConcreteStruct([
                    "segment_id"          => $issue->id_segment,
                    "issue_id"            => null,
                    "issue_create_date"   => null,
                    "issue_replies_count" => "0",
                    "issue_start_offset"  => "0",
                    "issue_end_offset"    => "0",
                    "issue_category"      => $new_category->label,
                    "category_options"    => $found_severity->options,
                    "issue_severity"      => $found_severity->label,
                    "issue_comment"       => null,
                    "target_text"         => null,
                    "issue_uid"           => null,
                    "warning_scope"       => null,
                    "warning_data"        => null,
                    "warning_severity"    => null
            ]);

        }

        return $categories;

    }


}
