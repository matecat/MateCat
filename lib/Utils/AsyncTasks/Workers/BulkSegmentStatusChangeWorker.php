<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/01/2019
 * Time: 10:57
 */

namespace AsyncTasks\Workers;


use Chunks_ChunkStruct;
use Database;
use Features;
use Features\SecondPassReview\Utils;
use Features\TranslationVersions\Model\SegmentTranslationEventModel;
use INIT;
use SegmentTranslationChangeVector;
use Stomp;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use Translations_SegmentTranslationDao;
use Users_UserDao;
use WordCount_CounterModel;

class BulkSegmentStatusChangeWorker extends AbstractWorker {

    protected $maxRequeueNum = 3;

    public function getLoggerName() {
        return 'bulk_segment_status_change.log';
    }

    /**
     * @param AbstractElement $queueElement
     *
     * @return mixed|void
     */
    public function process( AbstractElement $queueElement ) {
        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );
        $this->_doLog( 'data: ' . var_export( $queueElement->params->toArray(), true ) );

        $params = $queueElement->params->toArray();
        /** @var Chunks_ChunkStruct $chunk */
        $chunk         = new Chunks_ChunkStruct( $params['chunk']->toArray() ) ;
        $status      = $params['destination_status'] ;
        $client_id   = $params['client_id'];
        $user        = ( new Users_UserDao())->getByUid( $params['id_user'] );
        $source_page = Utils::revisionNumberToSourcePage( $params['revision_number'] );

        $this->_checkDatabaseConnection();

        $database = Database::obtain() ;
        $database->begin() ;

        foreach ( $params[ 'segment_ids' ] as $segment ) {

            $old_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $segment, $chunk->id );

            if ( empty( $old_translation ) ) {
                //no segment found
                continue;
            }

            $new_translation         = clone $old_translation;
            $new_translation->status = $status;

            Translations_SegmentTranslationDao::updateSegmentStatusBySegmentId( $chunk->id, $segment, $status );

            if ( $chunk->getProject()->hasFeature( Features::TRANSLATION_VERSIONS ) ) {
                $segmentTransaltionEvent = new SegmentTranslationEventModel( $old_translation, $new_translation, $user, $source_page ) ;
                $segmentTransaltionEvent->save();
            }
        }

        if ( !empty( $params[ 'segment_ids' ] ) ) {
            $counter = new WordCount_CounterModel();
            $counter->initializeJobWordCount( $chunk->id, $chunk->password );
        }

        $this->_doLog( 'completed' );

        $database->commit();

        if ( $client_id ) {
            $segment_ids = $params[ 'segment_ids' ]->toArray();
            $payload     = [
                    'segment_ids' => array_values( $segment_ids ),
                    'status'      => $status
            ];

            $message = json_encode( [
                    '_type' => 'bulk_segment_status_change',
                    'data'  => [
                            'id_job'    => $chunk->id,
                            'passwords' => $chunk->password,
                            'id_client' => $client_id,
                            'payload'   => $payload,
                    ]
            ] );

            $stomp = new Stomp( INIT::$QUEUE_BROKER_ADDRESS );
            $stomp->connect();
            $stomp->send( INIT::$SSE_NOTIFICATIONS_QUEUE_NAME,
                    $message,
                    [ 'persistent' => 'true' ]
            );
        }
    }

}