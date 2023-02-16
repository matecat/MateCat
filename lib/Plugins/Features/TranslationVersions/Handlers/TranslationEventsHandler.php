<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/06/2019
 * Time: 16:29
 */

namespace Features\TranslationVersions\Handlers;

use BasicFeatureStruct;
use Chunks_ChunkStruct;
use Exception;
use Features\ReviewExtended;
use Features\TranslationVersions\Model\TranslationEvent;
use FeatureSet;
use TransactionableTrait;

class TranslationEventsHandler {

    use TransactionableTrait;

    /**
     * @var TranslationEvent[]
     */
    protected $_events = [];

    /**
     * @var FeatureSet
     */
    protected $_featureSet;

    /**
     * @var Chunks_ChunkStruct
     */
    protected $_chunk;

    /** @var \Projects_ProjectStruct */
    protected $_project;

    /**
     * TranslationEventsHandler constructor.
     *
     * @param Chunks_ChunkStruct $chunkStruct
     */
    public function __construct( Chunks_ChunkStruct $chunkStruct ) {
        $this->_chunk = $chunkStruct;
    }

    /**
     * @return TranslationEvent[]
     */
    public function getEvents() {
        return $this->_events;
    }

    /**
     * @return TranslationEvent[]
     */
    public function getPersistedEvents() {
        return array_filter( $this->_events, function ( TranslationEvent $event ) {
            return $event->isPersisted();
        } );
    }

    /**
     * @param FeatureSet $featureSet
     */
    public function setFeatureSet( FeatureSet $featureSet ) {
        $this->_featureSet = $featureSet;
    }

    /**
     * @param TranslationEvent $eventModel
     */
    public function addEvent( TranslationEvent $eventModel ) {
        $this->_events[] = $eventModel;
    }

    /**
     * @return \Projects_ProjectStruct
     */
    public function getProject() {
        return $this->_project;
    }

    /**
     * @param \Projects_ProjectStruct $project
     *
     * @return $this
     */
    public function setProject( $project ) {
        $this->_project = $project;

        return $this;
    }

    /**
     * Save events
     *
     * @throws Exception
     */
    public function save() {
        $this->openTransaction();

        foreach ( $this->_events as $event ) {
            $event->save();
        }

        $basicFeatureStruct = $this->_featureSet->getFeaturesStructs();

        if( isset( $basicFeatureStruct[ 'review_extended' ] ) ){
            /** @var $reviewExtended ReviewExtended */
            $reviewExtended = $basicFeatureStruct[ 'review_extended' ]->toNewObject();
            $reviewExtended->processReviewTransitions( $this );
        }

        $this->commitTransaction();

    }

    /**
     * @return Chunks_ChunkStruct
     */
    public function getChunk() {
        return $this->_chunk;
    }

}