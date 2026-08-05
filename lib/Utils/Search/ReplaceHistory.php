<?php

namespace Utils\Search;

use Model\Search\ReplaceEventDAOInterface;
use Model\Search\ReplaceEventIndexDaoInterface;
use Model\Search\ReplaceEventStruct;
use PDOException;

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
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public function save(ReplaceEventStruct $eventStruct): int
    {
        return $this->replaceEventDAO->save($eventStruct);
    }

    /**
     * Undo steps the cursor back one version. The pre-replacement text + status of the current version
     * are restored by GetSearchController::updateSegments (the single text/audit writer), so undo only
     * moves the cursor here.
     *
     * @throws PDOException
     */
    public function undo(): int
    {
        $versionToRevert = $this->getCursor();
        $events = $this->get($versionToRevert);

        if (count($events) === 0) {
            return 0;
        }

        $this->replaceEventIndexDAO->save($this->idJob, $versionToRevert - 1);

        return count($events);
    }

    public function updateIndex(int $versionToMove): void
    {
        $this->replaceEventIndexDAO->save($this->idJob, $versionToMove);
    }
}