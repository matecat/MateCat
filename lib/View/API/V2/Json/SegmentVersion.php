<?php

namespace API\V2\Json;

use Chunks_ChunkStruct;
use FeatureSet;
use LQA\EntryStruct;
use Matecat\SubFiltering\MateCatFilter;

class SegmentVersion {

    private $featureSet;
    private $data;
    private $with_issues;

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk;

    /**
     * SegmentVersion constructor.
     *
     * @param Chunks_ChunkStruct $chunk
     * @param                    $data
     * @param bool               $with_issues
     * @param FeatureSet|null    $featureSet
     *
     * @throws \Exception
     */
    public function __construct( Chunks_ChunkStruct $chunk, $data, $with_issues = false, FeatureSet $featureSet = null ) {
        $this->data        = $data;
        $this->with_issues = $with_issues;
        $this->chunk       = $chunk;

        if ( $featureSet == null ) {
            $featureSet = new FeatureSet();
        }

        $this->featureSet = $featureSet;

    }

    /**
     * @return array
     * @throws \Exception
     */
    public function render() {
        $version = null;

        if ( $this->with_issues ) {
            return $this->renderItemsWithIssues();
        }

        return $this->renderItemsNormal();
    }

    protected function renderItemsWithIssues() {
        $out            = [];
        $issuesSubset   = [];
        $commentsSubset = [];

        $versionId = null;
        $version   = null;

        $issues_renderer = new SegmentTranslationIssue();

        foreach ( $this->data as $record ) {
            if ( !is_null( $versionId ) && $versionId != $record->id ) {

                if ( !empty( $issuesSubset ) ) {
                    // attach issues to version
                    $version[ 'issues' ] = array_map( function ( $item ) use ( $issues_renderer ) {
                        return $issues_renderer->renderItem( $item );
                    }, $issuesSubset );
                }

                $out[] = $version;

                $issuesSubset = [];
            }

            $version = $this->renderItem( $record );

            $version[ 'issues' ] = [];

            if ( !isset( $version[ 'diff' ] ) ) {
                $version[ 'diff' ] = json_decode( $record->raw_diff, true );
            }

            if ( !is_null( $record->qa_id_segment ) ) {
                $issuesSubset[] = ( new EntryStruct( [
                        'id'                  => $record->qa_id,
                        'id_segment'          => $record->qa_id_segment,
                        'id_job'              => $record->qa_id_job,
                        'id_category'         => $record->qa_id_category,
                        'severity'            => $record->qa_severity,
                        'translation_version' => $record->qa_translation_version,
                        'start_node'          => $record->qa_start_node,
                        'start_offset'        => $record->qa_start_offset,
                        'end_node'            => $record->qa_end_node,
                        'end_offset'          => $record->qa_end_offset,
                        'is_full_segment'     => $record->qa_is_full_segment,
                        'penalty_points'      => $record->qa_penalty_points,
                        'comment'             => $record->qa_comment,
                        'create_date'         => $record->qa_create_date,
                        'target_text'         => $record->qa_target_text,
                        'rebutted_at'         => $record->qa_rebutted_at,
                        'source_page'         => $record->qa_source_page
                ] ) )->setDiff( $version[ 'diff' ] );
            }

            $versionId = $record->id;
        }

        if ( !empty( $issuesSubset ) ) {
            $version[ 'issues' ] = array_map( function ( $item ) use ( $issues_renderer ) {
                return $issues_renderer->renderItem( $item );
            }, $issuesSubset );
        }

        $out[] = $version;

        return $out;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function renderItemsNormal() {
        $out = [];
        foreach ( $this->data as $record ) {
            $out[] = $this->renderItem( $record );
        }

        return $out;
    }

    /**
     * @param $version
     *
     * @return array
     * @throws \Exception
     */
    public function renderItem( $version ) {

        $featureSet = ( $this->featureSet !== null ) ? $this->featureSet : new \FeatureSet();
        $Filter = MateCatFilter::getInstance( $featureSet, $this->chunk->source, $this->chunk->target, [] );

        return [
                'id'              => (int)$version->id,
                'id_segment'      => (int)$version->id_segment,
                'id_job'          => (int)$version->id_job,
                'translation'     => $Filter->fromLayer0ToLayer2( $version->translation ),
                'version_number'  => (int)$version->version_number,
                'propagated_from' => (int)$version->propagated_from,
                'created_at'      => $version->creation_date,
        ];
    }

}
