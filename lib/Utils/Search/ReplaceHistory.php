<?php

namespace Utils\Search;

use Model\Search\ReplaceEventDAOInterface;
use Model\Search\ReplaceEventIndexDAOInterface;
use Model\Search\ReplaceEventStruct;
use Model\Translations\SegmentTranslationDao;

class ReplaceHistory {

    /**
     * @var int
     */
    private int $idJob;

    /**
     * @var ReplaceEventDAOInterface
     */
    private ReplaceEventDAOInterface $replaceEventDAO;

    /**
     * @var ReplaceEventIndexDAOInterface
     */
    private ReplaceEventIndexDAOInterface $replaceEventIndexDAO;

    /**
     * ReplaceHistory constructor.
     *
     * @param                                      $idJob
     * @param ReplaceEventDAOInterface             $replaceEventDAO
     * @param ReplaceEventIndexDAOInterface        $replaceEventIndexDAO
     * @param int                                  $ttl
     */
    public function __construct( $idJob, ReplaceEventDAOInterface $replaceEventDAO, ReplaceEventIndexDAOInterface $replaceEventIndexDAO, int $ttl = 0 ) {
        $this->idJob                = $idJob;
        $this->replaceEventDAO      = $replaceEventDAO;
        $this->replaceEventIndexDAO = $replaceEventIndexDAO;

        if ( $ttl ) {
            $this->replaceEventDAO->setTtl( $ttl );
            $this->replaceEventIndexDAO->setTtl( $ttl );
        }
    }

    /**
     * @param $version
     *
     * @return ReplaceEventStruct[]
     */
    public function get( $version ): array {
        return $this->replaceEventDAO->getEvents( $this->idJob, $version );
    }

    /**
     * @return int
     */
    public function getCursor(): int {
        return $this->replaceEventIndexDAO->getActualIndex( $this->idJob );
    }

    /**
     * @return int
     */
    public function redo(): int {
        $versionToMove = $this->getCursor() + 1;

        return $this->_moveToVersion( $versionToMove );
    }

    /**
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public function save( ReplaceEventStruct $eventStruct ): int {
        return $this->replaceEventDAO->save( $eventStruct );
    }

    /**
     * @return int
     */
    public function undo(): int {
        $versionToMove = $this->getCursor() - 1;

        return $this->_moveToVersion( $versionToMove );
    }

    /**
     * @param $versionToMove
     *
     * @return int
     */
    private function _moveToVersion( $versionToMove ): int {
        $events = $this->get( $versionToMove );

        if ( count( $events ) > 0 ) {
            $replacedEvents = SegmentTranslationDao::rebuildFromReplaceEvents( $events );

            $this->replaceEventIndexDAO->save( $this->idJob, $versionToMove );

            return $replacedEvents;
        }

        return 0;
    }

    /**
     * @param $versionToMove
     */
    public function updateIndex( $versionToMove ) {
        $this->replaceEventIndexDAO->save( $this->idJob, $versionToMove );
    }
}