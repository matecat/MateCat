<?php

namespace Utils\Search;

use Model\Search\ReplaceEventDAOInterface;
use Model\Search\ReplaceEventIndexDaoInterface;
use Model\Search\ReplaceEventStruct;
use Model\Translations\SegmentTranslationDao;

class ReplaceHistory
{

    /**
     * @var int
     */
    private int $idJob;

    /**
     * @var ReplaceEventDAOInterface
     */
    private ReplaceEventDAOInterface $replaceEventDAO;

    /**
     * @var ReplaceEventIndexDaoInterface
     */
    private ReplaceEventIndexDaoInterface $replaceEventIndexDAO;

    /**
     * ReplaceHistory constructor.
     *
     * @param int $idJob
     * @param ReplaceEventDAOInterface $replaceEventDAO
     * @param ReplaceEventIndexDaoInterface $replaceEventIndexDAO
     * @param int $ttl
     */
    public function __construct(int $idJob, ReplaceEventDAOInterface $replaceEventDAO, ReplaceEventIndexDaoInterface $replaceEventIndexDAO, int $ttl = 0)
    {
        $this->idJob = $idJob;
        $this->replaceEventDAO = $replaceEventDAO;
        $this->replaceEventIndexDAO = $replaceEventIndexDAO;

        if ($ttl) {
            $this->replaceEventDAO->setTtl($ttl);
            $this->replaceEventIndexDAO->setTtl($ttl);
        }
    }

    /**
     * @param int $version
     *
     * @return ReplaceEventStruct[]
     */
    public function get(int $version): array
    {
        return $this->replaceEventDAO->getEvents($this->idJob, $version);
    }

    /**
     * @return int
     */
    public function getCursor(): int
    {
        return $this->replaceEventIndexDAO->getActualIndex($this->idJob);
    }

    /**
     * @throws \PDOException
     */
    public function redo(): int
    {
        $versionToMove = $this->getCursor() + 1;

        return $this->_moveToVersion($versionToMove);
    }

    /**
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public function save(ReplaceEventStruct $eventStruct): int
    {
        return $this->replaceEventDAO->save($eventStruct);
    }

    /**
     * @throws \PDOException
     */
    public function undo(): int
    {
        $versionToMove = $this->getCursor() - 1;

        return $this->_moveToVersion($versionToMove);
    }

    /**
     * @throws \PDOException
     */
    private function _moveToVersion(int $versionToMove): int
    {
        $events = $this->get($versionToMove);

        if (count($events) > 0) {
            $replacedEvents = (new SegmentTranslationDao())->rebuildFromReplaceEvents($events);

            $this->replaceEventIndexDAO->save($this->idJob, $versionToMove);

            return $replacedEvents;
        }

        return 0;
    }

    public function updateIndex(int $versionToMove): void
    {
        $this->replaceEventIndexDAO->save($this->idJob, $versionToMove);
    }
}