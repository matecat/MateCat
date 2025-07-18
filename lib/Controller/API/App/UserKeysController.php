<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\ClientUserFacade;
use ReflectionException;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\TMS\TMSService;
use Utils\Tools\Utils;

class UserKeysController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function delete(): void {

        $request           = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate( $request[ 'key' ], $request[ 'description' ] );
        $mkDao             = $this->getMkDao();
        $userMemoryKeys    = $mkDao->disable( $memoryKeyToUpdate );
        $this->featureSet->run( 'postUserKeyDelete', $userMemoryKeys->tm_key->key, $this->user->uid );

        $this->response->json( [
                'errors'  => [],
                'data'    => $userMemoryKeys,
                "success" => true
        ] );


    }

    /**
     * @throws Exception
     */
    public function update(): void {

        $request           = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate( $request[ 'key' ], $request[ 'description' ] );
        $mkDao             = $this->getMkDao();
        $userMemoryKeys    = $mkDao->atomicUpdate( $memoryKeyToUpdate );

        $this->response->json( [
                'errors'  => [],
                'data'    => $userMemoryKeys,
                "success" => true
        ] );

    }

    /**
     * @throws Exception
     */
    public function newKey(): void {

        $request           = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate( $request[ 'key' ], $request[ 'description' ] );
        $mkDao             = $this->getMkDao();
        $userMemoryKeys    = $mkDao->create( $memoryKeyToUpdate );

        $this->featureSet->run( 'postTMKeyCreation', [ $userMemoryKeys ], $this->user->uid );

        $this->response->json( [
                'errors'  => [],
                'data'    => $userMemoryKeys,
                "success" => true
        ] );

    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function info(): void {

        $request           = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate( $request[ 'key' ], $request[ 'description' ] );
        $mkDao             = $this->getMkDao();
        $userMemoryKeys    = $mkDao->read( $memoryKeyToUpdate );

        $this->response->json( $this->getKeyUsersInfo( $userMemoryKeys ) );

    }

    /**
     * @throws NotFoundException
     * @throws Exception
     */
    public function share(): void {

        $request           = $this->validateTheRequest();
        $memoryKeyToUpdate = $this->getMemoryToUpdate( $request[ 'key' ], $request[ 'description' ] );
        $emailList         = Utils::validateEmailList( $request[ 'emails' ] );
        $mkDao             = $this->getMkDao();
        $userMemoryKeys    = $mkDao->read( $memoryKeyToUpdate );

        if ( empty( $userMemoryKeys ) ) {
            throw new NotFoundException( "No user memory keys found" );
        }

        ( new TmKeyManager() )->shareKey( $emailList, $userMemoryKeys[ 0 ], $this->user );

        $this->response->json( [
                'errors'  => [],
                'data'    => $userMemoryKeys,
                "success" => true
        ] );

    }

    /**
     * @param array $userMemoryKeys
     *
     * @return array
     */
    protected function getKeyUsersInfo( array $userMemoryKeys ): array {

        if(empty($userMemoryKeys)){
            return [
                    'errors'  => [],
                    "data"    => [],
                    "success" => true
            ];
        }

        $_userStructs = [];
        foreach ( $userMemoryKeys[ 0 ]->tm_key->getInUsers() as $userStruct ) {
            $_userStructs[] = new ClientUserFacade( $userStruct );
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
    private function validateTheRequest(): array {
        $key         = filter_var( $this->request->param( 'key' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $emails      = filter_var( $this->request->param( 'emails' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $description = filter_var( $this->request->param( 'description' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );

        // check for eventual errors on the input passed
        if ( empty( $key ) ) {
            throw new InvalidArgumentException( "Key missing", -2 );
        }

        // Prevent XSS attack
        // ===========================
        // POC. Try to add this string in the input:
        // <details x=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx:2 open ontoggle="prompt(document.cookie);">
        // in this case, an error MUST be thrown
        if ( $_POST[ 'description' ] and $_POST[ 'description' ] !== $description ) {
            throw new InvalidArgumentException( "<span>Resource names cannot contain the following characters:</span><ul><li><</li><li>\"</li><li>'</li></ul>", -3 );
        }

        return [
                'key'         => $key,
                'emails'      => $emails,
                'description' => ( !empty( $description ) ) ? $description : null,
        ];
    }

    /**
     * @return MemoryKeyDao
     */
    private function getMkDao(): MemoryKeyDao {
        return new MemoryKeyDao( Database::obtain() );
    }

    /**
     * @param      $key
     * @param null $description
     *
     * @return MemoryKeyStruct
     * @throws Exception
     */
    private function getMemoryToUpdate( $key, $description = null ): MemoryKeyStruct {
        $tmService = new TMSService();

        //validate the key
        $tmService->checkCorrectKey( $key );

        $tmKeyStruct       = new TmKeyStruct();
        $tmKeyStruct->key  = $key;
        $tmKeyStruct->name = $description;
        $tmKeyStruct->tm   = true;
        $tmKeyStruct->glos = true;

        $memoryKeyToUpdate         = new MemoryKeyStruct();
        $memoryKeyToUpdate->uid    = $this->user->uid;
        $memoryKeyToUpdate->tm_key = $tmKeyStruct;

        return $memoryKeyToUpdate;
    }
}