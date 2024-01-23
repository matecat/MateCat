<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use Engine;
use InvalidArgumentException;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyStruct;
use TMS\TMSService;

class MyMemoryController extends KleinController
{
    protected function afterConstruct()
    {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Create a MM key and assign to the logged user
     */
    public function create()
    {
        try {
            $json = $this->request->body();
            $json = json_decode($json, true);

            $name = null;
            $key = null;

            if(!isset($json['name'])){
                throw new InvalidArgumentException('Missing `name` param', 403);
            }

            if(isset($json['key'])){
                $key = filter_var( $json['key'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH );
            }

            $name = filter_var( $json['name'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH );

            if($key !== null){
                $newKey = $this->checkTheKeyAndAssignToUser($key, $name);
            } else {
                $newKey = $this->createANewKeyAndAssignToUser($name);
            }

            $this->response->status()->setCode( 200 );
            $this->response->json( [
                'key' => $newKey
            ] );
            exit();

        } catch (\Exception $exception){
            $this->response->status()->setCode( $exception->getCode() );
            $this->response->json( [
                'errors' => [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage()
                ]
            ] );
            exit();
        }
    }

    /**
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    private function createANewKeyAndAssignToUser($name)
    {
        $tms = Engine::getInstance( 1 );
        $newKey = $tms->createMyMemoryKey();

        $this->saveMemoryKey($newKey->key, $name);

        return $newKey->key;
    }

    /**
     * @param $key
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    private function checkTheKeyAndAssignToUser($key, $name)
    {
        $tmxHandler = new TMSService();
        $keyExists = $tmxHandler->checkCorrectKey( $key );

        if($keyExists === false){
            throw new \Exception($key . " is not a valid key");
        }

        $this->saveMemoryKey($key, $name);

        return $key;
    }

    /**
     * @param string $key
     * @param string $name
     * @throws \Exception
     */
    private function saveMemoryKey($key, $name)
    {
        $tmKeyStruct       = new TmKeyManagement_TmKeyStruct();
        $tmKeyStruct->key  = $key;
        $tmKeyStruct->name = $name;
        $tmKeyStruct->tm   = true;
        $tmKeyStruct->glos = true;

        $mkDao = new TmKeyManagement_MemoryKeyDao();

        $newMemoryKey         = new TmKeyManagement_MemoryKeyStruct();
        $newMemoryKey->uid    = $this->user->uid;
        $newMemoryKey->tm_key = $tmKeyStruct;

        try {
            $mkDao->create($newMemoryKey);
        } catch (\Exception $exception){
            $mkDao->atomicUpdate($newMemoryKey);
        }
    }
}
