<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 21/06/2019
 * Time: 16:29
 */

namespace Features\TranslationVersions\Model;


use Chunks_ChunkStruct;
use FeatureSet;
use TransactionableTrait;

class BatchEventCreator {

    use TransactionableTrait ;
    /**
     * @var SegmentTranslationEventModel[]
     */
    protected $_events ;

    /**
     * @var FeatureSet
     */
    protected $_featureSet ;

    /**
     * @var Chunks_ChunkStruct
     */
    protected   $_chunk;

    public function  __construct( Chunks_ChunkStruct $chunkStruct ) {
        $this->_chunk = $chunkStruct ;
    }

    public function getEvents() {
        return $this->_events ;
    }

    public function setFeatureSet( FeatureSet $featureSet ) {
        $this->_featureSet = $featureSet ;
    }

    public function addEventModel( SegmentTranslationEventModel $eventModel ) {
        $this->_events [] = $eventModel ;
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