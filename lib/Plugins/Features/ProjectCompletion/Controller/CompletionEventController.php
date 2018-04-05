<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 31/05/2017
 * Time: 14:57
 */


namespace Features\ProjectCompletion\Controller;

use API\V2\Exceptions\ValidationError;
use API\V2\Validators\ChunkPasswordValidator;
use BaseKleinViewController;
use Chunks_ChunkCompletionEventDao;
use Chunks_ChunkCompletionEventStruct;
use Chunks_ChunkStruct;
use Database;
use Exception;
use Exceptions_RecordNotFound;
use Log;
use LQA\ChunkReviewDao;
use Utils;

class CompletionEventController extends BaseKleinViewController {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return $this
     */
    public function setChunk( $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    /**
     * @var Chunks_ChunkCompletionEventStruct
     */
    protected $event;

    /**
     * @param Chunks_ChunkCompletionEventStruct $event
     */
    public function setEvent( Chunks_ChunkCompletionEventStruct $event ) {
        $this->event = $event;
    }

    /**
     * @throws Exceptions_RecordNotFound
     * @throws ValidationError
     * @throws \Exceptions\ValidationError
     */
    public function delete() {
        // TODO: The following code does not really belong here. It's related to ReviewImproved
        // and should be properly decoupled.

        $project  = $this->chunk->getProject( 60 * 60 );
        $undoable = true;

        $this->featureSet = FeatureSet::loadForProject( $project );

        $undoable = $this->featureSet->filter( 'filterIsChunkCompletionUndoable', $undoable, $project, $this->chunk );

        if ( $undoable ) {
            $this->__evalDelete();
            $this->response->code( 200 );
            $this->response->send();
        } else {
            $this->response->code( 400 );
        }

    }

    /**
     * @throws Exception
     */
    protected function afterConstruct() {

        $Controller = $this;
        $Validator  = new ChunkPasswordValidator( $this );
        $Validator->onSuccess( function () use ( $Controller, $Validator ) {

            $event = ( new Chunks_ChunkCompletionEventDao() )->getByIdAndChunk( $Controller->getParams()[ 'id_event' ], $Validator->getChunk() );

            if ( !$event ) {
                throw new Exceptions_RecordNotFound( "Event Not Found.", 404 );
            }

            $Controller->setChunk( $Validator->getChunk() );
            $Controller->setEvent( $event );

        } );

        $this->appendValidator( $Validator );

    }

    /**
     * @throws ValidationError
     * @throws \Exceptions\ValidationError
     */
    private function __evalDelete() {
        $review = ChunkReviewDao::findOneChunkReviewByIdJobAndPassword(
                $this->request->id_job, $this->request->password
        );

        $undo_data = $review->getUndoData();
        if ( is_null( $undo_data ) ) {
            throw new ValidationError( 'undo data is not available' );
        }

        $this->__validateUndoData( $undo_data );

        $review->is_pass              = $undo_data[ 'is_pass' ];
        $review->penalty_points       = $undo_data[ 'penalty_points' ];
        $review->reviewed_words_count = $undo_data[ 'reviewed_words_count' ];
        $review->undo_data            = null;

        Database::obtain()->begin();
        ChunkReviewDao::updateStruct( $review, [
                'fields' => [
                        'is_pass', 'penalty_points', 'reviewed_words_count', 'undo_data'
                ]
        ] );

        Log::doLog( "CompletionEventController deleting event: " . var_export( $this->event->getArrayCopy(), true ) );

        ( new Chunks_ChunkCompletionEventDao() )->deleteEvent( $this->event );
        Database::obtain()->commit();
    }

    /**
     * @param $undo_data
     *
     * @throws ValidationError
     */
    private function __validateUndoData( $undo_data ) {
        try {
            Utils::ensure_keys( $undo_data, [
                    'reset_by_event_id', 'penalty_points', 'reviewed_words_count', 'is_pass'
            ] );

        } catch ( Exception $e ) {
            throw new ValidationError( 'undo data is missing some keys. ' . $e->getMessage() );
        }

        if ( $undo_data[ 'reset_by_event_id' ] != (string)$this->event->id ) {
            throw new ValidationError( 'event does not match with latest revision data' );
        }

    }


}