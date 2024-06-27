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
use Exception;
use Features;
use Features\ReviewExtended\ReviewUtils;
use Features\TranslationVersions\Handlers\TranslationEventsHandler;
use Features\TranslationVersions\Model\TranslationEvent;
use Stomp\Exception\StompException;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;
use Translations_SegmentTranslationDao;
use Users_UserDao;
use WordCount\CounterModel;


class BulkSegmentStatusChangeWorker extends AbstractWorker {

    protected int $maxRequeueNum = 3;

    public function getLoggerName(): string {
        return 'bulk_segment_status_change.log';
    }

    /**
     * @param AbstractElement $queueElement
     *
     * @return void
     * @throws \ReflectionException
     * @throws StompException
     * @throws EndQueueException
     * @throws Exception
     */
    public function process( AbstractElement $queueElement ) {
        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );
        $this->_checkDatabaseConnection();
        $this->_doLog( 'data: ' . var_export( $queueElement->params->toArray(), true ) );

        $params = $queueElement->params->toArray();

        $chunk       = new Chunks_ChunkStruct( $params[ 'chunk' ]->toArray() );
        $status      = $params[ 'destination_status' ];
        $client_id   = $params[ 'client_id' ];
        $user        = ( new Users_UserDao() )->getByUid( $params[ 'id_user' ] );
        $source_page = ReviewUtils::revisionNumberToSourcePage( $params[ 'revision_number' ] );


        $database = Database::obtain();
        $database->begin();

        $batchEventCreator = new TranslationEventsHandler( $chunk );
        $batchEventCreator->setFeatureSet( $chunk->getProject()->getFeaturesSet() );
        $batchEventCreator->setProject( $chunk->getProject() );

        foreach ( $params[ 'segment_ids' ] as $segment_id ) {

            $old_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $segment_id, $chunk->id );

            if ( empty( $old_translation ) ) {
                //no segment found
                continue;
            }

            $new_translation                   = clone $old_translation;
            $new_translation->status           = $status;
            $new_translation->translation_date = date( "Y-m-d H:i:s" );

            Translations_SegmentTranslationDao::updateTranslationAndStatusAndDate( $new_translation );

            if ( $chunk->getProject()->hasFeature( Features::TRANSLATION_VERSIONS ) ) {
                $segmentTranslationEvent = new TranslationEvent( $old_translation, $new_translation, $user, $source_page );
                $batchEventCreator->addEvent( $segmentTranslationEvent );
            }
        }

        $batchEventCreator->save();

        if ( !empty( $params[ 'segment_ids' ] ) ) {
            $counter = new CounterModel();
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

            $message = [
                    '_type' => 'bulk_segment_status_change',
                    'data'  => [
                            'id_job'    => $chunk->id,
                            'passwords' => $chunk->password,
                            'id_client' => $client_id,
                            'payload'   => $payload,
                    ]
            ];

            $this->publishToSseTopic( $message );

        }
    }

}