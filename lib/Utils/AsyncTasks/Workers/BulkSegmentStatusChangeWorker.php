<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/01/2019
 * Time: 10:57
 */

namespace AsyncTasks\Workers;


use Jobs_JobStruct;
use SegmentTranslationChangeVector;
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

    }

}