<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/01/2019
 * Time: 10:57
 */

namespace AsyncTasks\Workers;


use INIT;
use Jobs_JobStruct;
use SegmentTranslationChangeVector;
use Stomp;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use Translations_SegmentTranslationDao;
use WordCount_Counter;

class BulkSegmentStatusChangeWorker extends AbstractWorker {

    protected $maxRequeueNum  = 3 ;

    public function getLoggerName() {
        return 'bulk_segment_status_change.log' ;
    }

    /**
     * @param AbstractElement $queueElement
     */
    public function process( AbstractElement $queueElement ) {
        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );
        $this->_doLog('data: ' . var_export( $queueElement->params->toArray(), true ) ) ;

        $params = $queueElement->params->toArray() ;
        /** @var Jobs_JobStruct $job */
        $job         = new Jobs_JobStruct( $params['job']->toArray() ) ;
        $status      = $params['destination_status'] ;
        $client_id   = $params['client_id'];

        $this->_checkDatabaseConnection();

        $database = \Database::obtain() ;
        $database->begin() ;

        foreach( $params['segment_ids'] as $segment ) {
            $old_translation = Translations_SegmentTranslationDao::findBySegmentAndJob($segment, $job->id);
            $new_translation = clone $old_translation ;
            $new_translation->status = $status ;

            Translations_SegmentTranslationDao::updateSegmentStatusBySegmentId( $job->id, $segment, $status );

            $translation = new SegmentTranslationChangeVector( $new_translation ) ;
            $translation->setOldTranslation( $old_translation );

            $job->getProject()->getFeatures()->run('updateRevisionScore', $translation );
        }

        if ( !empty( $params['segment_ids'] ) ) {
            $counter = new WordCount_Counter();
            $counter->initializeJobWordCount( $job->id, $job->password );
        }

        $this->_doLog('completed') ;

        $database->commit();

        if ( $client_id ) {
            $segment_ids = $params['segment_ids']->toArray();
            $payload = [
                    'segment_ids' => array_values( $segment_ids ),
                    'status'      => $status
            ] ;

            $message = json_encode( array(
                    '_type' => 'bulk_segment_status_change',
                    'data' => array(
                            'id_job'    => $job->id,
                            'passwords' => $job->password,
                            'id_client' => $client_id,
                            'payload'   => $payload,
                    )
            ));

            $stomp = new Stomp( INIT::$QUEUE_BROKER_ADDRESS );
            $stomp->connect();
            $stomp->send( INIT::$SSE_NOTIFICATIONS_QUEUE_NAME,
                    $message,
                    array( 'persistent' => 'true' )
            );
        }
    }

}