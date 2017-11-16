<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 31/05/2017
 * Time: 14:57
 */


namespace Features\ProjectCompletion\Controller ;

use API\V2\Exceptions\ValidationError;
use API\V2\Validators\ChunkPasswordValidator;
use BaseKleinViewController;
use Chunks_ChunkCompletionEventDao;
use Chunks_ChunkCompletionEventStruct;
use Database;
use Exception;
use Exceptions_RecordNotFound;
use Features\ProjectCompletion\Model\ProjectCompletionStatusModel;
use FeatureSet;
use Log;
use LQA\ChunkReviewDao;
use Utils;

class CompletionEventController extends BaseKleinViewController {

    /**
     * @var ChunkPasswordValidator ;
     */
    protected $validator ;

    /**
     * @var Chunks_ChunkCompletionEventStruct
     */
    protected $event ;

    public function delete() {
        // TODO: The following code does not really belong here. It's relted to ReviewImproved
        // and should be properly decoupled.

        $project = $this->validator->getChunk()->getProject() ;
        $undoable = true ;

        $this->featureSet->loadForProject( $project );

        $undoable = $this->featureSet->filter('filterIsChunkCompletionUndoable', $undoable, $project,
                $this->validator->getChunk() );

        if ( $undoable ) {
            $this->__evalDelete() ;
            $this->response->code( 200 ) ;
            $this->response->send();
        } else {
            $this->response->code( 400 );
        }
    }

    protected function afterConstruct() {
        $this->validator = new ChunkPasswordValidator( $this->request );
        $this->validator->validate() ;

        $this->event = ( new Chunks_ChunkCompletionEventDao() )
                ->getByIdAndChunk( $this->request->id_event, $this->validator->getChunk() );

        if ( !$this->event ) {
            throw new Exceptions_RecordNotFound() ;
        }
    }

    private function __evalDelete() {
        $review = ChunkReviewDao::findOneChunkReviewByIdJobAndPassword(
                $this->request->id_job, $this->request->password
        ) ;

        $undo_data = $review->getUndoData();
        if ( is_null( $undo_data ) ) {
            throw new ValidationError('undo data is not available') ;
        }

        $this->__validateUndoData( $undo_data );

        $review->is_pass = $undo_data['is_pass'];
        $review->penalty_points = $undo_data['penalty_points'];
        $review->reviewed_words_count = $undo_data['reviewed_words_count'] ;
        $review->undo_data = null ;

        Database::obtain()->begin();
        ChunkReviewDao::updateStruct( $review, ['fields' => [
                'is_pass', 'penalty_points', 'reviewed_words_count', 'undo_data'
        ] ] );

        Log::doLog("CompletionEventController deleting event: " . var_export( $this->event->getArrayCopy(), true ) );

        ( new Chunks_ChunkCompletionEventDao())->deleteEvent( $this->event ) ;
        Database::obtain()->commit();
    }

    private function __validateUndoData( $undo_data ) {
        try {
            Utils::ensure_keys( $undo_data, [
                    'reset_by_event_id', 'penalty_points', 'reviewed_words_count', 'is_pass'
            ]) ;

        } catch( Exception $e ) {
            throw new ValidationError( 'undo data is missing some keys. ' . $e->getMessage() );
        }

        if ( $undo_data['reset_by_event_id'] != (string) $this->event->id ) {
            throw new ValidationError('event does not match with latest revision data') ;
        }

    }


}