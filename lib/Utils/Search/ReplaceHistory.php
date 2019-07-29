<?php

use Search\ReplaceEventStruct;

class Search_ReplaceHistory {

    /**
     * @var int
     */
    private $idJob;

    /**
     * @var Search_ReplaceEventDAOInterface
     */
    private $replaceEventDAO;

    /**
     * @var Search_ReplaceEventIndexDAOInterface
     */
    private $replaceEventIndexDAO;

    /**
     * Search_ReplaceHistory constructor.
     *
     * @param                                      $idJob
     * @param Search_ReplaceEventDAOInterface      $replaceEventDAO
     * @param Search_ReplaceEventIndexDAOInterface $replaceEventIndexDAO
     * @param null                                 $ttl
     */
    public function __construct( $idJob, Search_ReplaceEventDAOInterface $replaceEventDAO, Search_ReplaceEventIndexDAOInterface $replaceEventIndexDAO, $ttl = null ) {
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
    public function get( $version ) {
        return $this->replaceEventDAO->getEvents( $this->idJob, $version );
    }

    /**
     * @return int
     */
    public function getCursor() {
        return $this->replaceEventIndexDAO->getActualIndex( $this->idJob );
    }

    /**
     * @return int
     */
    public function redo() {
        $versionToMove = $this->getCursor() + 1;

        return $this->_moveToVersion( $versionToMove );
    }

    /**
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public function save( ReplaceEventStruct $eventStruct ) {
        return $this->replaceEventDAO->save( $eventStruct );
    }

    /**
     * @return int
     */
    public function undo() {
        $versionToMove = $this->getCursor() - 1;

        return $this->_moveToVersion( $versionToMove );
    }

    /**
     * @param $versionToMove
     *
     * @return int
     */
    private function _moveToVersion( $versionToMove ) {
        $events = $this->get( $versionToMove );

        if ( count( $events ) > 0 ) {
            $replacedEvents = Translations_SegmentTranslationDao::rebuildFromReplaceEvents( $events );

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