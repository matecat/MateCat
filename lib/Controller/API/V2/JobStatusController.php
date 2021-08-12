<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 26/03/2018
 * Time: 12:35
 */

namespace API\V2;

use AMQHandler;
use API\V2\Validators\ChunkPasswordValidator;
use Constants_TranslationStatus;
use Features\ReviewExtended\ReviewUtils;
use Features\SecondPassReview;
use Projects_ProjectStruct;
use Translations_SegmentTranslationDao;
use WorkerClient;


class JobStatusController extends BaseChunkController {

    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    protected function afterConstruct() {

        $chunkValidator = new ChunkPasswordValidator( $this );
        $chunkValidator->onSuccess( function () use ( $chunkValidator ) {
            $this->chunk   = $chunkValidator->getChunk();
            $this->project = $this->chunk->getProject();
        } );

        $this->appendValidator( $chunkValidator );
    }

    public function changeSegmentsStatus() {

        $this->return404IfTheJobWasDeleted();

        $segments_id = $this->sanitizeSegmentIDs( $this->request->segments_id );
        $status      = strtoupper( $this->request->status );
        $source_page = null ;

        if ( $this->request->revision_number ) {
            $validRevisions = ReviewUtils::validRevisionNumbers( $this->chunk ) ;
            if ( !in_array( $this->request->revision_number, $validRevisions ) ) {
                $this->response->code( 400 ) ;
                $this->response->json( [ 'error' => 'Invalid revision number' ] );
                return;
            }
            $source_page = ReviewUtils::revisionNumberToSourcePage( $this->request->revision_number ) ;
        }

        if ( in_array( $status, [
                Constants_TranslationStatus::STATUS_TRANSLATED, Constants_TranslationStatus::STATUS_APPROVED
        ] ) ) {
            $unchangeble_segments = Translations_SegmentTranslationDao::getUnchangebleStatus(
                    $this->chunk, $segments_id, $status, $source_page
            );
            $segments_id          = array_diff( $segments_id, $unchangeble_segments );

            if ( !empty( $segments_id ) ) {

                try {
                    WorkerClient::init( new AMQHandler() );
                    WorkerClient::enqueue( 'JOBS', '\AsyncTasks\Workers\BulkSegmentStatusChangeWorker',
                            [
                                    'segment_ids'        => $segments_id,
                                    'client_id'          => $this->request->client_id,
                                    'chunk'              => $this->chunk,
                                    'destination_status' => $status,
                                    'id_user'            => ( $this->userIsLogged() ? $this->getUser()->uid : null ),
                                    'is_review'          => ( $status == Constants_TranslationStatus::STATUS_APPROVED ),
                                    'revision_number'    => $this->request->revision_number
                            ], [ 'persistent' => true ]
                    );
                } catch ( \Exception $e ) {
                    $this->response->json( [ 'error_message' => $e->getMessage(), 'data' => true, 'unchangeble_segments' => $segments_id ] );

                    return;
                }
            }

            $this->response->json( [ 'data' => true, 'unchangeble_segments' => $unchangeble_segments ] );
        }
    }

    protected function sanitizeSegmentIDs( $segment_list ) {
        foreach ( $segment_list as $pos => $integer ) {
            $result = (int)$integer;
            if ( empty( $result ) ) {
                unset( $segment_list[ $pos ] );
                continue;
            }
            $segment_list[ $pos ] = $result;
        }

        return array_unique( $segment_list );
    }

}