<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 30/08/2017
 * Time: 12:02
 */

namespace Features\Dqf\Service\Struct\Response;


use Features\Dqf\Service\Struct\BaseStruct;

class FileResponseStruct extends BaseStruct {

    public $id ;
    public $name ;
    public $segmentSize ;
    public $segmentsUploaded ;
    public $wordCount ;
    public $integratorFileMap ;

}