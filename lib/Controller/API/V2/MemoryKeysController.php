<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 24/08/17
 * Time: 17.29
 *
 */

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use View\API\V2\Json\MemoryKeys;

class MemoryKeysController extends KleinController {

    public function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function listKeys() {

        $keyQuery      = new MemoryKeyStruct();
        $keyQuery->uid = $this->user->uid;

        $memoryKeyDao = new MemoryKeyDao();
        $keyList      = $memoryKeyDao->read( $keyQuery );

        $formatter = new MemoryKeys( $keyList );
        $this->response->json( $formatter->render() );

    }

}