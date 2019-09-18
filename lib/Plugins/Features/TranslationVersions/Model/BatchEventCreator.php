<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/06/2019
 * Time: 16:29
 */

namespace Features\TranslationVersions\Model;


use Chunks_ChunkStruct;
use Features\ProjectCompletion\Model\EventModel;
use FeatureSet;
use TransactionableTrait;

class BatchEventCreator {

    use TransactionableTrait ;
    /**
     * @var SegmentTranslationEventModel[]
     */
    protected $_events = [];

    /**
     * @var FeatureSet
     */
    protected $_featureSet ;

    /**
     * @var Chunks_ChunkStruct
     */
    protected   $_chunk;

    /** @var \Projects_ProjectStruct */
    protected $_project;

    public function  __construct( Chunks_ChunkStruct $chunkStruct ) {
        $this->_chunk = $chunkStruct ;
    }

    public function getEvents() {
        return $this->_events ;
    }

    public function getPersistedEvents() {
        return array_filter( $this->_events, function( SegmentTranslationEventModel $event ) {
            return $event->isPersisted() ;
        }) ;
    }

    public function setFeatureSet( FeatureSet $featureSet ) {
        $this->_featureSet = $featureSet ;
    }

    public function addEventModel( SegmentTranslationEventModel $eventModel ) {
        $this->_events [] = $eventModel ;
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



    public function save() {
        $this->openTransaction() ;

        foreach ( $this->_events as $event ) {
            $event->save();
        }

        $this->_featureSet->run('batchEventCreationSaved', $this) ;
        $this->commitTransaction() ;
    }

    /**
     * @return Chunks_ChunkStruct
     */
    public function getChunk() {
        return $this->_chunk;
    }

}