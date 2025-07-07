<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 26/03/2018
 * Time: 12:35
 */

namespace Controller\API\V2;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Features\ReviewExtended\ReviewUtils;
use Model\Translations\SegmentTranslationDao;
use Utils\AsyncTasks\Workers\BulkSegmentStatusChangeWorker;
use Utils\Constants\TranslationStatus;
use WorkerClient;


class JobStatusController extends KleinController {
    use ChunkNotFoundHandlerTrait;
    protected function afterConstruct() {

        $chunkValidator = new ChunkPasswordValidator( $this );
        $chunkValidator->onSuccess( function () use ( $chunkValidator ) {
            $this->chunk = $chunkValidator->getChunk();
        } );

        $this->appendValidator( $chunkValidator );
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Change the status of segments based on the provided parameters.
     * @return void
     * @throws Exception
     * @see api_v2_routes.php
     */
    public function changeSegmentsStatus() {

        $this->return404IfTheJobWasDeleted();

        $segments_id = $this->sanitizeSegmentIDs( $this->request->param( 'segments_id' ) );
        $status      = strtoupper( $this->request->param( 'status' ) );
        $source_page = null;

        if ( $this->request->param( 'revision_number' ) ) {
            $validRevisions = ReviewUtils::validRevisionNumbers( $this->chunk );
            if ( !in_array( $this->request->param( 'revision_number' ), $validRevisions ) ) {
                $this->response->code( 400 );
                $this->response->json( [ 'error' => 'Invalid revision number' ] );

                return;
            }
            $source_page = ReviewUtils::revisionNumberToSourcePage( $this->request->param( 'revision_number' ) );
        }

        if ( in_array( $status, [
                TranslationStatus::STATUS_TRANSLATED, TranslationStatus::STATUS_APPROVED, TranslationStatus::STATUS_APPROVED2
        ] ) ) {
            $unchangeable_segments = SegmentTranslationDao::getUnchangeableStatus(
                    $this->chunk, $segments_id, $status, $source_page
            );
            $segments_id           = array_diff( $segments_id, $unchangeable_segments );

            if ( !empty( $segments_id ) ) {

                try {
                    WorkerClient::enqueue( 'JOBS', BulkSegmentStatusChangeWorker::class,
                            [
                                    'segment_ids'        => $segments_id,
                                    'client_id'          => $this->request->param( 'client_id' ),
                                    'chunk'              => $this->chunk,
                                    'destination_status' => $status,
                                    'id_user'            => ( $this->isLoggedIn() ? $this->getUser()->uid : null ),
                                    'is_review'          => ( $status == TranslationStatus::STATUS_APPROVED ),
                                    'revision_number'    => $this->request->param( 'revision_number' )
                            ], [ 'persistent' => true ]
                    );
                } catch ( Exception $e ) {
                    $this->response->json( [ 'error_message' => $e->getMessage(), 'data' => true, 'unchangeble_segments' => $segments_id ] );

                    return;
                }
            }

            $this->response->json( [ 'data' => true, 'unchangeble_segments' => $unchangeable_segments ] );
        }
    }

    protected function sanitizeSegmentIDs( $segment_list ): array {
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