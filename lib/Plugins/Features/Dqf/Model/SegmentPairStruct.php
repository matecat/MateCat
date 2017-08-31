<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/07/2017
 * Time: 14:56
 */

namespace Features\Dqf\Model;


use DataAccess_AbstractDaoObjectStruct;

class SegmentPairStruct extends DataAccess_AbstractDaoObjectStruct {

    public $sourceSegmentId ;
    public $clientId ;
    public $targetSegment ;
    public $editedSegment ;
    public $time ;
    public $segmentOriginId ;
    public $mtEngineId ;
    public $mtEngineOtherName ;
    public $matchRate ;

}