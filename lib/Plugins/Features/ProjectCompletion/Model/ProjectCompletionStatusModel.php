<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/06/2017
 * Time: 18:13
 */

namespace Features\ProjectCompletion\Model;


use API\V2\Exceptions\AuthenticationError;
use Chunks_ChunkCompletionEventDao;
use Chunks_ChunkStruct;
use Exception;
use Exceptions\NotFoundException;
use Exceptions\ValidationError;
use FeatureSet;
use Projects_ProjectStruct;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
use Utils;

class ProjectCompletionStatusModel {

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    protected $cachedStatus;

    public function __construct( Projects_ProjectStruct $project ) {
        $this->project = $project ;
    }

    /**
     * @throws NotFoundException
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws AuthenticationError
     */
    public function getStatus() {
        if ( is_null( $this->cachedStatus ) ) {
            $this->cachedStatus = $this->populateStatus();
        }
        return $this->cachedStatus ;
    }

    /**
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws EndQueueException
     * @throws ValidationError
     * @throws AuthenticationError
     * @throws Exception
     */
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

    /**
     * @throws Exception
     */
    private function dataForChunkStatus ( Chunks_ChunkStruct $chunk, $is_review ) {
        $record = Chunks_ChunkCompletionEventDao::lastCompletionRecord( $chunk, array(
                'is_review' => $is_review
        ) );

        if ( $record ) {
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