<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 01/10/14
 * Time: 16.31
 * 
 */

class getPersonalKeysController extends ajaxController {

    public function __construct(){

        //SESSION ENABLED
        parent::sessionStart();
        parent::__construct();

        //TODO receive data query


    }

    public function doAction(){

        $con = Database::obtain();
        $_keyList = new TmKeyManagement_MemoryKeyDao($con);

        $dh = new TmKeyManagement_MemoryKeyStruct( array( 'uid' => $_SESSION['uid'] ) );

        $keyList = $_keyList->read( $dh );
//todo: 'used' will be the job's ID
        $this->result['data']['used'] = array();
        foreach( $keyList as $memKey ){
            //all keys are available in this condition ( we are creating a project
            $this->result['data']['available'][] = $memKey->tm_key;
        }



    }

} 