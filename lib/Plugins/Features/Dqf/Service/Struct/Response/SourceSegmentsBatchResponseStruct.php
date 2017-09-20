<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/03/2017
 * Time: 14:35
 */

namespace Features\Dqf\Service\Struct\Response;
use Features\Dqf\Service\Struct\BaseStruct;

class SourceSegmentsBatchResponseStruct extends BaseStruct {

    public $message ;
    public $remaining ;
    public $dqfFileId ;

    public $segmentList ;
    public $segmentListUpdated ;


}