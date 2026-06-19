<?php

namespace Utils\AsyncTasks\Workers;

use Exception;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureCodes;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use Plugins\Features\ReviewExtended\BatchReviewProcessor;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\TranslationEventsHandler;
use ReflectionException;
use TypeError;
use Utils\ActiveMQ\AMQHandler;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

class BulkSegmentStatusChangeWorker extends AbstractWorker
{
    protected int $maxRequeueNum = 3;

    private UserDao $userDao;

    /**
     * @throws ReflectionException
     */
    public function __construct(AMQHandler $queueHandler, IDatabase $database, ?UserDao $userDao = null)
    {
        parent::__construct($queueHandler, $database);
        $this->userDao = $userDao ?? new UserDao($this->database);
    }

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
     * @throws TypeError
     */
    public function process(AbstractElement $queueElement): void
    {
        if (!$queueElement instanceof QueueElement) {
            return;
        }

        $this->_checkForReQueueEnd($queueElement);
        $this->_checkDatabaseConnection();
        $this->_doLog('data: ' . var_export($queueElement->params->toArray(), true));

        $params = $queueElement->params->toArray();

        $chunk = $this->createJobStruct($params['chunk']);
        $status = $params['destination_status'];
        $client_id = $params['client_id'];
        $user = $this->userDao->getByUid($params['id_user']);
        $source_page = ReviewUtils::revisionNumberToSourcePage($params['revision_number']);

        $this->database->begin();

        $segmentTranslationDao = new SegmentTranslationDao($this->database);

        $project = $chunk->getProject();
        $featureSet = FeatureSet::forProject($project, $this->database);

        $batchEventCreator = $this->createTranslationEventsHandler($chunk);
        $batchEventCreator->setFeatureSet($featureSet);
        $batchEventCreator->setProject($project);

        $old_translations = $segmentTranslationDao->getAllSegmentsByIdListAndJobId($params['segment_ids'], (int)$chunk->id);

        $new_translations = [];

        if (empty($old_translations)) {
            return;
        }

        foreach ($old_translations as $old_translation) {
            $new_translation = clone $old_translation;
            $new_translation->status = $status;
            $new_translation->translation_date = date("Y-m-d H:i:s");

            $new_translations[] = $new_translation;

            if ($featureSet->hasFeature(FeatureCodes::TRANSLATION_VERSIONS)) {
                try {
                    $segmentTranslationEvent = $this->createTranslationEvent($old_translation, $new_translation, $user, $source_page);
                } catch (Exception $e) {
                    throw new EndQueueException($e->getMessage(), $e->getCode(), $e);
                }

                $batchEventCreator->addEvent($segmentTranslationEvent);
            }
        }

        $segmentTranslationDao->updateTranslationAndStatusAndDateByList($new_translations);

        $batchEventCreator->save(new BatchReviewProcessor());

        $this->_doLog('completed');

        $this->database->commit();

        if ($client_id) {
            $segment_ids = $params['segment_ids'];
            $payload = [
                'segment_ids' => array_values($segment_ids),
                'status' => $status
            ];

            $message = [
                '_type' => 'bulk_segment_status_change',
                'data' => [
                    'id_job' => $chunk->id,
                    'passwords' => $chunk->password,
                    'id_client' => $client_id,
                    'payload' => $payload,
                ]
            ];

            $this->publishToNodeJsClients($message);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function createJobStruct(array $params): JobStruct
    {
        return new JobStruct($params);
    }

    protected function createTranslationEventsHandler(JobStruct $chunk): TranslationEventsHandler
    {
        return new TranslationEventsHandler($chunk);
    }

    /**
     * @throws Exception
     */
    protected function createTranslationEvent(
        SegmentTranslationStruct $oldTranslation,
        SegmentTranslationStruct $newTranslation,
        ?UserStruct $user,
        int $sourcePage
    ): TranslationEvent {
        return new TranslationEvent($oldTranslation, $newTranslation, $user, $sourcePage);
    }

}
