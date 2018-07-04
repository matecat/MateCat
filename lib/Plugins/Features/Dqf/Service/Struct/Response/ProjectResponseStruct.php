<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/07/2017
 * Time: 11:07
 */

namespace Features\Dqf\Service\Struct\Response;


use Exception;
use Features\Dqf\Service\Struct\BaseStruct;

class ProjectResponseStruct extends BaseStruct {

    public $id ;
    public $completionTimestamp ;
    public $creationTimestamp ;
    public $name ;
    // public $ownerUser ;
    public $status ;
    public $type ;
    public $updateTimestamp ;
    // public $user ;
    public $uuid ;
    public $level ;
    public $isReturn ;
    public $files ;
    public $integrator ;
    public $integratorProjectMap ;
    public $language ;
    public $projectSettings ;
    public $projectReviewSetting ;
    public $projectTargetLangs ;
    public $fileProjectTargetLangs ;
    public $active ;

    protected $_user ;
    protected $_ownerUser ;

    public function __set($method, $data) {
        if ( $method == 'user' || $method == 'ownerUser' ) {
            $new_name = "_$method";
            $this->$new_name = new UserResponseStruct( $data );
        }
    }

    public function __get($method) {
        $method = "_$method";
        if ( property_exists($this, $method) ) {
            return $this->$method ;
        }

        throw new Exception("Property does not exist: $method");
    }

}