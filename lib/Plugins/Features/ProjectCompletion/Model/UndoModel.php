<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 01/06/2017
 * Time: 12:57
 */

namespace Features\ProjectCompletion\Model;


class UndoModel {

    /**
     * @var \Chunks_ChunkCompletionEventStruct
     */
    protected $event ;

    public function __construct($event) {
        $this->event = $event ;
    }



}