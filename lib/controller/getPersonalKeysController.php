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
        $keysList = new TmKeyManagement_MemoryKeyDao($con);

        $dh = new TmKeyManagement_MemoryKeyStruct( array( 'uid' => $_SESSION['uid'] ) );
        Log::doLog( $keysList->read( $dh ) );

        $this->result['data'] = $keysList->read( $dh );

    }

} 