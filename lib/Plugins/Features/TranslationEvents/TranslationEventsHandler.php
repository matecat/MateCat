<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/06/2019
 * Time: 16:29
 */

namespace Plugins\Features\TranslationEvents;

use Exception;
use Model\DataAccess\IDatabase;
use Model\DataAccess\TransactionalTrait;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Plugins\Features\ReviewExtended\BatchReviewProcessor;
use Plugins\Features\TranslationEvents\Model\TranslationEvent;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use Plugins\Features\TranslationEvents\Model\TranslationEventStruct;
use ReflectionException;
use TypeError;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;

class TranslationEventsHandler
{

    use TransactionalTrait;

    protected function getTransactionalDatabase(): IDatabase
    {
        return $this->translationEventDao->getDatabaseHandler();
    }

    /**
     * @var TranslationEvent[]
     */
    protected array $_events = [];

    /**
     * @var FeatureSet
     */
    protected FeatureSet $_featureSet;

    /**
     * @var JobStruct
     */
    protected JobStruct $_chunk;

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $_project;

    private TranslationEventDao $translationEventDao;

    /**
     * TranslationEventsHandler constructor.
     *
     * @param JobStruct $chunkStruct
     * @param TranslationEventDao $translationEventDao
     */
    public function __construct(
        JobStruct $chunkStruct,
        TranslationEventDao $translationEventDao,
    ) {
        $this->_chunk = $chunkStruct;
        $this->translationEventDao = $translationEventDao;
    }

    /**
     * @return TranslationEvent[]
     */
    public function getEvents(): array
    {
        return $this->_events;
    }

    /**
     * @return TranslationEvent[]
     */
    public function getPreparedEvents(): array
    {
        return array_filter($this->_events, function (TranslationEvent $event) {
            return $event->isPrepared();
        });
    }

    /**
     * @param FeatureSet $featureSet
     */
    public function setFeatureSet(FeatureSet $featureSet): void
    {
        $this->_featureSet = $featureSet;
    }

    /**
     * @param TranslationEvent $eventModel
     */
    public function addEvent(TranslationEvent $eventModel): void
    {
        $this->_events[] = $eventModel;
    }

    /**
     * @return ProjectStruct
     */
    public function getProject(): ProjectStruct
    {
        return $this->_project;
    }

    /**
     * @param ProjectStruct $project
     *
     * @return $this
     */
    public function setProject(ProjectStruct $project): TranslationEventsHandler
    {
        $this->_project = $project;

        return $this;
    }


    /**
     * @throws ValidationError
     * @throws Exception
     * @throws TypeError
     */
    public function prepareEventStruct(TranslationEvent $event): void
    {
        if (
            in_array($event->getWantedTranslation()['status'], TranslationStatus::$REVISION_STATUSES) &&
            $event->getSourcePage() < SourcePages::SOURCE_PAGE_REVISION
        ) {
            throw new ValidationError('Setting revised state from translation is not allowed.', -2000);
        }

        $eventStruct = new TranslationEventStruct();
        $eventStruct->id_job = $event->getWantedTranslation()['id_job'];
        $eventStruct->id_segment = $event->getWantedTranslation()['id_segment'];
        $eventStruct->uid = ($event->getUser() != null ? ($event->getUser()->uid ?? 0) : 0);
        $eventStruct->status = $event->getWantedTranslation()['status'];
        $eventStruct->version_number = $event->getWantedTranslation()['version_number'] ?? 0;
        $eventStruct->source_page = $event->getSourcePage();

        if ($event->isPropagationSource()) {
            $eventStruct->time_to_edit = $event->getWantedTranslation()['time_to_edit'];
        }

        $eventStruct->setTimestamp('create_date', time());

        // set as prepared
        $event->setTranslationEventStruct($eventStruct);
        $event->setPrepared(true);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    private function saveEvent(TranslationEvent $event): void
    {
        $eventStruct = $event->getTranslationEventStruct();

        if (!$event->isFinalRevisionFlagAllowed()) {
            $eventStruct->final_revision = 0;
        } else {
            $eventStruct->final_revision = (int)($eventStruct->source_page > SourcePages::SOURCE_PAGE_TRANSLATE && !$event->isADraftChange());
        }

        $result = $this->translationEventDao->insertStruct($eventStruct);
        $eventStruct->id = $result !== false ? $result : null;
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    private function removeOldFinalRevisionFlag(TranslationEvent $event): void
    {
        if (!empty($event->getUnsetFinalRevision())) {
            $segment = $event->getSegmentStruct();
            if ($segment === null) {
                throw new Exception('Segment not found for final revision flag removal');
            }
            $this->translationEventDao->unsetFinalRevisionFlag(
                (int)$this->getChunk()->id,
                [$segment->id],
                $event->getUnsetFinalRevision()
            );
        }
    }

    /**
     * Save events
     *
     * @param BatchReviewProcessor $batchReviewProcessor
     *
     * @throws Exception
     * @throws TypeError
     */
    public function save(BatchReviewProcessor $batchReviewProcessor): void
    {
        $this->openTransaction();

        try {
            foreach ($this->_events as $event) {
                $this->prepareEventStruct($event);
            }

            $batchReviewProcessor->setChunk($this->getChunk());
            $batchReviewProcessor->setPreparedEvents($this->getPreparedEvents());
            $batchReviewProcessor->process();

            foreach ($this->_events as $event) {
                $this->removeOldFinalRevisionFlag($event);
                $this->saveEvent($event);
            }
        } catch (Exception $e) {
            $this->rollbackTransaction();
            throw $e;
        } finally {
            $this->commitTransaction();
        }
    }

    /**
     * @return JobStruct
     */
    public function getChunk(): JobStruct
    {
        return $this->_chunk;
    }

}