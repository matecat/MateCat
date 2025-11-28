<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/01/2019
 * Time: 10:57
 */

namespace Utils\AsyncTasks\Workers;


use Exception;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureCodes;
use Model\Jobs\JobStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Users\UserDao;
use Plugins\Features\ReviewExtended\BatchReviewProcessor;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use ReflectionException;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;


class BulkSegmentStatusChangeWorker extends AbstractWorker
{

    protected int $maxRequeueNum = 3;

    public function getLoggerName(): string
    {
        return 'bulk_segment_status_change.log';
    }

    /**
     * @param AbstractElement $queueElement
     *
     * @return void
     * @throws ReflectionException
     * @throws EndQueueException
     * @throws Exception
     */
    public function process(AbstractElement $queueElement): void
    {
        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd($queueElement);
        $this->_checkDatabaseConnection();
        $this->_doLog('data: ' . var_export($queueElement->params->toArray(), true));

        $params = $queueElement->params->toArray();

        $chunk       = new JobStruct($params[ 'chunk' ]);
        $status      = $params[ 'destination_status' ];
        $client_id   = $params[ 'client_id' ];
        $user        = (new UserDao())->getByUid($params[ 'id_user' ]);
        $source_page = ReviewUtils::revisionNumberToSourcePage($params[ 'revision_number' ]);


        $database = Database::obtain();
        $database->begin();

        $batchEventCreator = new TranslationEventsHandler($chunk);
        $batchEventCreator->setFeatureSet($chunk->getProject()->getFeaturesSet());
        $batchEventCreator->setProject($chunk->getProject());

        $old_translations = SegmentTranslationDao::getAllSegmentsByIdListAndJobId($params[ 'segment_ids' ], $chunk->id);

        $new_translations = [];

        if (empty($old_translations)) {
            //no segment found
            return;
        }

        foreach ($old_translations as $old_translation) {
            $new_translation                   = clone $old_translation;
            $new_translation->status           = $status;
            $new_translation->translation_date = date("Y-m-d H:i:s");

            $new_translations[] = $new_translation;

            if ($chunk->getProject()->hasFeature(FeatureCodes::TRANSLATION_VERSIONS)) {
                try {
                    $segmentTranslationEvent = new TranslationEvent($old_translation, $new_translation, $user, $source_page);
                } catch (Exception $e) {
                    // job archived or deleted, runtime exception on TranslationEvent creation
                    throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
                }

                $batchEventCreator->addEvent($segmentTranslationEvent);
            }
        }

        SegmentTranslationDao::updateTranslationAndStatusAndDateByList($new_translations);

        $batchEventCreator->save(new BatchReviewProcessor());

        $this->_doLog('completed');

        $database->commit();

        if ($client_id) {
            $segment_ids = $params[ 'segment_ids' ];
            $payload     = [
                    'segment_ids' => array_values($segment_ids),
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

            $this->publishToNodeJsClients($message);
        }
    }

}