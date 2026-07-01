<?php

namespace Utils\AsyncTasks\Workers;

use Exception;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Propagation\PropagationTotalStruct;
use Model\Translations\SegmentTranslationStruct;
use PDOException;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use ReflectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

class PropagationWorker extends AbstractWorker
{
    private TranslationVersionDao $translationVersionsDao;

    /**
     * @throws ReflectionException
     */
    public function __construct(
        AMQHandler $queueHandler,
        IDatabase $database,
        ?TranslationVersionDao $translationVersionsDao = null
    ) {
        parent::__construct($queueHandler, $database);
        $this->translationVersionsDao = $translationVersionsDao ?? new TranslationVersionDao($this->database);
    }

    /**
     * @inheritDoc
     * @throws EndQueueException
     * @throws Exception
     */
    public function process(AbstractElement $queueElement): void
    {
        if (!$queueElement instanceof QueueElement) {
            throw new EndQueueException('Invalid queue element type');
        }
        $this->_checkForReQueueEnd($queueElement);
        $this->_checkDatabaseConnection();

        $this->propagateTranslation($this->rebuildObjects($queueElement->params));
    }

    /**
     * @param array<string, mixed> $structures
     *
     * @throws Exception
     */
    protected function propagateTranslation(array $structures): void
    {
        $propagationTotalStruct = $structures['propagationAnalysis'];
        if (!$propagationTotalStruct instanceof PropagationTotalStruct) {
            return;
        }

        /** @var SegmentTranslationStruct $propagatorSegment */
        $propagatorSegment = $structures['translationStructTemplate'];

        if (true === $structures['execute_update'] and !empty($propagationTotalStruct->getSegmentsForPropagation())) {
            try {
                $static_field_values = [
                    'id_segment' => $structures['id_segment'],
                    'id_job' => $propagatorSegment['id_job'],
                    'segment_hash' => $propagatorSegment['segment_hash'],
                    'translation' => $propagatorSegment['translation'],
                    'status' => $propagatorSegment['status'],
                    'translation_date' => $propagatorSegment['translation_date'],
                    'autopropagated_from' => $propagatorSegment['autopropagated_from'],
                    'serialized_errors_list' => $propagatorSegment['serialized_errors_list'],
                    'warning' => $propagatorSegment['warning'],
                ];

                $chunked_segments = array_chunk($propagationTotalStruct->getAllToPropagate(), 20, true);

                foreach ($chunked_segments as $segments) {
                    $updateValues = $static_field_values;
                    $propagated_ids_placeholder = [];

                    foreach ($segments as $i => $segment) {
                        $propagated_ids_placeholder[] = ':propagated_id_' . $i;
                        $updateValues['propagated_id_' . $i] = $segment['id_segment'];
                    }

                    $propagationSql = "
                            UPDATE segment_translations
                            SET translation = :translation,
                                status = :status,
                                translation_date = :translation_date,
                                autopropagated_from = :autopropagated_from,
                                serialized_errors_list = :serialized_errors_list,
                                warning = :warning
                            WHERE id_segment != :id_segment
                              AND id_job = :id_job
                              AND segment_hash = :segment_hash
                              AND id_segment IN ( " . implode(",", $propagated_ids_placeholder) . " )
                        ";

                    $pdo = $this->database->getConnection();
                    $stmt = $pdo->prepare($propagationSql);

                    $stmt->execute($updateValues);

                    $stmt->closeCursor();

                    if (!empty($propagationTotalStruct->getPropagatedIdsToUpdateVersion())) {
                        $filteredIds = [];
                        $segmentIdsForVersionIncrementMap = $propagationTotalStruct->getPropagatedIdsToUpdateVersion();
                        $segmentsToIncrementMap = array_filter($segments, function ($segment) use ($segmentIdsForVersionIncrementMap, &$filteredIds) {
                            if (array_key_exists($segment['id_segment'], $segmentIdsForVersionIncrementMap)) {
                                $filteredIds[] = $segment['id_segment'];

                                return true;
                            }

                            return false;
                        });

                        $this->translationVersionsDao->savePropagationVersions(
                            $propagatorSegment,
                            $structures['id_segment'],
                            $structures['job'],
                            array_values($segmentsToIncrementMap),
                        );

                        $increaseVersionSql = "
                            UPDATE segment_translations SET version_number = version_number + 1
                            WHERE id_segment != :id_segment
                              AND id_job = :id_job
                              AND segment_hash = :segment_hash
                              AND id_segment IN ( " . implode(",", $filteredIds) . " )
                        ";

                        $stmt = $pdo->prepare($increaseVersionSql);

                        $stmt->execute([
                            'id_segment' => $structures['id_segment'],
                            'id_job' => $propagatorSegment['id_job'],
                            'segment_hash' => $propagatorSegment['segment_hash']
                        ]);

                        $stmt->closeCursor();
                    }
                }
            } catch (PDOException $e) {
                throw new EndQueueException(
                    "Error in propagating Translation: " . $e->getCode() . ": " . $e->getMessage()
                    . "\n"
                    . $propagationSql
                    . "\n"
                    . ($increaseVersionSql ?? '')
                    . "\n"
                    . var_export($propagatorSegment, true)
                    . "\n"
                    . var_export($propagationTotalStruct->getPropagatedIds(), true)
                );
            }
        }
    }

    /**
     * Cast to the proper objects from payload
     *
     * @return array<string, mixed>
     */
    protected function rebuildObjects(Params $params): array
    {
        $paramsArray = $params->toArray();

        return [
            'translationStructTemplate' => new SegmentTranslationStruct($paramsArray['translationStructTemplate']),
            'id_segment' => $params->id_segment,
            'job' => new JobStruct($paramsArray['job']),
            'project' => new ProjectStruct($paramsArray['project']),
            'propagationAnalysis' => new PropagationTotalStruct($paramsArray['propagationAnalysis']),
            'execute_update' => $params->execute_update
        ];
    }

}
