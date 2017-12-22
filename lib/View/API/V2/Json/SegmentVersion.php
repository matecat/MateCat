<?php

namespace API\V2\Json  ;

use API\V2\Json\SegmentTranslationIssue;
use DataAccess\ShapelessConcreteStruct;
use LQA\EntryStruct;

class SegmentVersion {

    private $data ;
    private $with_issues ;

    /**
     * SegmentVersion constructor.
     *
     * @param      $data
     * @param bool $with_issues
     */
    public function __construct( $data, $with_issues = false ) {
        $this->data = $data ;
        $this->with_issues = $with_issues ;
    }

    /**
     * @return array
     */
    public function render() {
        $version    = null ;

        if ( $this->with_issues ) {
            return $this->renderItemsWithIssues() ;
        }
        else {
            return $this->renderItemsNormal();
        }

        return $out;
    }

    protected function renderItemsWithIssues() {
        $out = [] ;
        $issuesSubset = [] ;
        $commentsSubset = [] ;

        $versionId = null;
        $version = null ;

        $issues_renderer = new SegmentTranslationIssue();

        foreach($this->data as $record) {
            if ( !is_null( $versionId ) && $versionId != $record->id ) {

                if ( !empty( $issuesSubset ) ) {
                    // attach issues to version
                    $version['issues'] = array_map(function( $item ) use ($issues_renderer) {
                        return $issues_renderer->renderItem( $item ) ;
                    }, $issuesSubset );
                }

                $out[]  = $version ;

                $issuesSubset = [];
            }

            $version = $this->renderItem( $record ) ;

            $version['issues'] = [];

            if ( !isset( $version['diff'] ) ) {
                $version['diff'] = json_decode( $record->raw_diff, true );
            }

            if ( !is_null($record->qa_id_segment )) {
                $issuesSubset[] = ( new EntryStruct([
                     'id'                  => $record->qa_id,
                     'id_segment'          => $record->qa_id_segment,
                     'id_job'              => $record->qa_id_job,
                     'id_category'         => $record->qa_id_category,
                     'severity'            => $record->qa_severity,
                     'translation_version' => $record->qa_translation_version,
                     'start_node'          => $record->qa_start_node,
                     'start_offset'    => $record->qa_start_offset,
                     'end_node'        => $record->qa_end_node,
                     'end_offset'      => $record->qa_end_offset,
                     'is_full_segment' => $record->qa_is_full_segment,
                     'penalty_points'  => $record->qa_penalty_points,
                     'comment'     => $record->qa_comment,
                     'create_date' => $record->qa_create_date,
                     'target_text' => $record->qa_target_text,
                     'rebutted_at' => $record->qa_rebutted_at
                ]))->setDiff( $version['diff'] ) ;
            }

            $versionId = $record->id ;
        }

        if ( !empty( $issuesSubset ) ) {
            $version['issues'] = array_map(function( $item ) use ($issues_renderer) {
                return $issues_renderer->renderItem( $item ) ;
            }, $issuesSubset );
        }

        $out[] = $version ;

        return $out;
    }

    /**
     * @return array
     */
    protected function renderItemsNormal() {
        $out = [] ;
        foreach($this->data as $record) {
            $out[] = $this->renderItem($record);
        }
        return $out ;
    }

    /**
     * @param $version
     *
     * @return array
     */
    public function renderItem( $version ) {
        return array(
                'id'              => (int)$version->id,
                'id_segment'      => (int)$version->id_segment,
                'id_job'          => (int)$version->id_job,
                'translation'     => \CatUtils::rawxliff2view( $version->translation ),
                'version_number'  => (int)$version->version_number,
                'propagated_from' => (int)$version->propagated_from,
                'created_at'      => $version->creation_date,
        );
    }

}
