<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/06/2017
 * Time: 18:13
 */

namespace Features\ProjectCompletion\Model;


use Chunks_ChunkCompletionEventDao;
use Chunks_ChunkStruct;
use Features;
use FeatureSet;
use Utils;

class ProjectCompletionStatusModel {

    /**
     * @var \Projects_ProjectStruct
     */
    protected $project ;

    protected $cachedStatus;

    public function __construct( \Projects_ProjectStruct $project ) {
        $this->project = $project ;
    }

    public function getStatus() {
        if ( is_null( $this->cachedStatus ) ) {
            $this->cachedStatus = $this->populateStatus();
        }
        return $this->cachedStatus ;
    }

    private function populateStatus() {
        $response = [] ;
        $response['revise'] = [] ;
        $response['translate'] = [] ;

        $response['id'] = $this->project->id ;

        $any_uncomplete = false;

        foreach( $this->project->getChunks() as $chunk ) {

            $translate = $this->dataForChunkStatus($chunk, false) ;
            $revise    = $this->dataForChunkStatus($chunk, true) ;

            $featureSet = new FeatureSet();
            $featureSet->loadForProject( $this->project );

            $revise['password'] = $featureSet->filter('filter_job_password_to_review_password',
                    $chunk->password,
                    $chunk->id
            );

            $response['translate'][] = $translate ;
            $response['revise'][]    = $revise ;

            if (! ( $revise['completed'] && $translate['completed'] ) ) $any_uncomplete = true;
        }

        $response['completed'] = !$any_uncomplete ;

        return $response ;
    }

    private function dataForChunkStatus ( Chunks_ChunkStruct $chunk, $is_review ) {
        $record = Chunks_ChunkCompletionEventDao::lastCompletionRecord( $chunk, array(
                'is_review' => $is_review
        ) );

        if ( $record != false ) {
            $is_completed = true;
            $completed_at = Utils::api_timestamp( $record['create_date'] );
            $event_id = $record['id_event'];
        } else {
            $is_completed = false;
            $completed_at = null;
            $event_id = null;
        }

        return array(
                'id'       =>  $chunk->id,
                'password' => $chunk->password,
                'completed' => $is_completed,
                'completed_at' => $completed_at,
                'event_id' => $event_id
        );
    }



}