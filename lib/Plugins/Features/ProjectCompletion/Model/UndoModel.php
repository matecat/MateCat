<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 01/06/2017
 * Time: 12:57
 */

namespace Plugins\Features\ProjectCompletion\Model;


use Model\ChunksCompletion\ChunkCompletionEventStruct;

class UndoModel
{

    protected ChunkCompletionEventStruct $event;

    public function __construct(ChunkCompletionEventStruct $event)
    {
        $this->event = $event;
    }


}