<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use InvalidArgumentException;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
use TmKeyManagement_TmKeyStruct;
use Utils;

class ShareKeyController extends KleinController
{
    protected function afterConstruct()
    {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Share a key with another user
     */
    public function share()
    {
        try {
            $json = $this->request->body();
            $json = json_decode($json, true);

            if(!isset($json['key'])){
                throw new InvalidArgumentException('Missing `key` param');
            }

            if(!isset($json['name'])){
                throw new InvalidArgumentException('Missing `name` param');
            }

            if(!isset($json['email'])){
                throw new InvalidArgumentException('Missing `email` param');
            }

            $key = filter_var( $json['key'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH );
            $name = filter_var( $json['name'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH );
            $email = filter_var( $json['email'], FILTER_SANITIZE_EMAIL  );

            Utils::validateEmailAddress($email);

            $tmKeyStruct       = new TmKeyManagement_TmKeyStruct();
            $tmKeyStruct->key  = $key;
            $tmKeyStruct->tm   = true;
            $tmKeyStruct->glos = true;

            $mkDao = new TmKeyManagement_MemoryKeyDao();

            $memoryKeyToUpdate         = new TmKeyManagement_MemoryKeyStruct();
            $memoryKeyToUpdate->uid    = $this->user->uid;
            $memoryKeyToUpdate->tm_key = $tmKeyStruct;

            $userMemoryKeys = $mkDao->read( $memoryKeyToUpdate );

            if(empty($userMemoryKeys)){
                throw new InvalidArgumentException('No memory key found');
            }

            $userMemoryKeys[0]->tm_key->name = $name;

            (new TmKeyManagement_TmKeyManagement())->shareKey([$email], $userMemoryKeys[0], $this->user);

            $this->response->status()->setCode( 200 );
            $this->response->json( [
                'success' => true
            ] );
            exit();

        } catch (\Exception $exception){
            $this->response->status()->setCode( 500 );
            $this->response->json( [
                'errors' => [
                    'code' => 0,
                    'message' => $exception->getMessage()
                ]
            ] );
            exit();
        }
    }
}