<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 24/08/17
 * Time: 17.29
 *
 */

namespace API\V2;


use API\V2\Json\MemoryKeys;
use API\V2\Validators\LoginValidator;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;

class MemoryKeysController extends KleinController {

    public function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function listKeys(){

        $keyQuery = new TmKeyManagement_MemoryKeyStruct();
        $keyQuery->uid = $this->user->uid;

        $memoryKeyDao = new TmKeyManagement_MemoryKeyDao();
        $keyList = $memoryKeyDao->read( $keyQuery );

        $formatter = new MemoryKeys( $keyList );
        $this->response->json( $formatter->render() );

    }

}