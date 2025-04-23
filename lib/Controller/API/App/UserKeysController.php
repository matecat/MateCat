<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Database;
use Exception;
use Exceptions\NotFoundException;
use InvalidArgumentException;
use Klein\Response;
use Log;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyManagement;
use TmKeyManagement_TmKeyStruct;
use TMS\TMSService;
use Users_ClientUserFacade;
use Utils;

class UserKeysController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function delete(): Response
    {
        try {
            $request = $this->validateTheRequest();
            $memoryKeyToUpdate = $this->getMemoryToUpdate($request['key'], $request['description']);
            $mkDao = $this->getMkDao();
            $userMemoryKeys = $mkDao->disable( $memoryKeyToUpdate );
            $this->featureSet->run('postUserKeyDelete', $userMemoryKeys->tm_key->key, $this->user->uid );

            return $this->response->json([
                'errors'  => [],
                'data' => $userMemoryKeys,
                "success" => true
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    public function update(): Response
    {
        try {
            $request = $this->validateTheRequest();
            $memoryKeyToUpdate = $this->getMemoryToUpdate($request['key'], $request['description']);
            $mkDao = $this->getMkDao();
            $userMemoryKeys = $mkDao->atomicUpdate( $memoryKeyToUpdate );

            return $this->response->json([
                'errors'  => [],
                'data' => $userMemoryKeys,
                "success" => true
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    public function newKey(): Response
    {
        try {
            $request = $this->validateTheRequest();
            $memoryKeyToUpdate = $this->getMemoryToUpdate($request['key'], $request['description']);
            $mkDao = $this->getMkDao();
            $userMemoryKeys = $mkDao->create( $memoryKeyToUpdate );
            $this->featureSet->run( 'postTMKeyCreation', [ $userMemoryKeys ], $this->user->uid );

            return $this->response->json([
                'errors'  => [],
                'data' => $userMemoryKeys,
                "success" => true
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    public function info(): Response
    {
        try {
            $request = $this->validateTheRequest();
            $memoryKeyToUpdate = $this->getMemoryToUpdate($request['key'], $request['description']);
            $mkDao = $this->getMkDao();
            $userMemoryKeys = $mkDao->read( $memoryKeyToUpdate );

            return $this->response->json($this->getKeyUsersInfo( $userMemoryKeys ));
        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    public function share(): Response
    {
        try {
            $request = $this->validateTheRequest();
            $memoryKeyToUpdate = $this->getMemoryToUpdate($request['key'], $request['description']);
            $emailList = Utils::validateEmailList($request['emails']);
            $mkDao = $this->getMkDao();
            $userMemoryKeys = $mkDao->read( $memoryKeyToUpdate );

            if(empty($userMemoryKeys)){
                throw new NotFoundException("No user memory keys found");
            }

            (new TmKeyManagement_TmKeyManagement())->shareKey($emailList, $userMemoryKeys[0], $this->user);

            return $this->response->json([
                'errors'  => [],
                'data' => $userMemoryKeys,
                "success" => true
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @param array $userMemoryKeys
     * @return array
     */
    protected function getKeyUsersInfo( array $userMemoryKeys ): array
    {

        $_userStructs = [];
        foreach( $userMemoryKeys[0]->tm_key->getInUsers() as $userStruct ){
            $_userStructs[] = new Users_ClientUserFacade( $userStruct );
        }

        return [
            'errors'  => [],
            "data"    => $_userStructs,
            "success" => true
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $key = filter_var( $this->request->param( 'key' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $emails = filter_var( $this->request->param( 'emails' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $description = filter_var( $this->request->param( 'description' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );

        // check for eventual errors on the input passed
        if ( empty( $key ) ) {
            throw new InvalidArgumentException("Key missing", -2);
        }

        // Prevent XSS attack
        // ===========================
        // POC. Try to add this string in the input:
        // <details x=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx:2 open ontoggle="prompt(document.cookie);">
        // in this case, an error MUST be thrown
        if($_POST['description'] and $_POST['description'] !== $description){
            throw new InvalidArgumentException("Invalid key description", -3);
        }

        return [
            'key' => $key,
            'emails' => $emails,
            'description' => $description,
        ];
    }

    /**
     * @return TmKeyManagement_MemoryKeyDao
     */
    private function getMkDao()
    {
        return new TmKeyManagement_MemoryKeyDao( Database::obtain() );
    }

    /**
     * @param $key
     * @param null $description
     * @return TmKeyManagement_MemoryKeyStruct
     * @throws Exception
     */
    private function getMemoryToUpdate($key, $description = null)
    {
        $tmService = new TMSService();

        //validate the key
        try {
            $keyExists = $tmService->checkCorrectKey( $key );
        } catch ( Exception $e ) {
            /* PROVIDED KEY IS NOT VALID OR WRONG, $keyExists IS NOT SET */
            Log::doJsonLog( $e->getMessage() );
        }

        if ( !isset( $keyExists ) || $keyExists === false ) {
            Log::doJsonLog( __METHOD__ . " -> TM key is not valid." );
            throw new InvalidArgumentException( "TM key is not valid.", -4 );
        }

        $tmKeyStruct       = new TmKeyManagement_TmKeyStruct();
        $tmKeyStruct->key  = $key;
        $tmKeyStruct->name = $description;
        $tmKeyStruct->tm   = true;
        $tmKeyStruct->glos = true;

        $memoryKeyToUpdate         = new TmKeyManagement_MemoryKeyStruct();
        $memoryKeyToUpdate->uid    = $this->user->uid;
        $memoryKeyToUpdate->tm_key = $tmKeyStruct;

        return $memoryKeyToUpdate;
    }
}