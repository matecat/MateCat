<?php

namespace API\App;

use AbstractControllers\KleinController;
use API\Commons\Validators\LoginValidator;
use Database;
use Exception;
use FilesStorage\AbstractFilesStorage;
use INIT;
use InvalidArgumentException;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyStruct;
use TMS\TMSFile;
use TMS\TMSService;

class LoadTMXController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function newTM(): void {

        $request   = $this->validateTheRequest();
        $TMService = new TMSService();
        $file      = $TMService->uploadFile();

        $uuids = [];

        foreach ( $file as $fileInfo ) {

            if ( AbstractFilesStorage::pathinfo_fix( strtolower( $fileInfo->name ), PATHINFO_EXTENSION ) !== 'tmx' ) {
                throw new Exception( "Please upload a TMX.", -8 );
            }

            $file = new TMSFile(
                    $fileInfo->file_path,
                    $request[ 'tm_key' ],
                    $fileInfo->name
            );

            $TMService->addTmxInMyMemory( $file, $this->user );
            $uuids[] = [ "uuid" => $file->getUuid(), "name" => $file->getName() ];

            $this->featureSet->run( 'postPushTMX', $file, $this->user );

            /*
             * We update the KeyRing only if this is NOT the Default MyMemory Key
             *
             * If it is NOT the default the key belongs to the user, so it's correct to update the user keyring.
             */
            if ( $request[ 'tm_key' ] != INIT::$DEFAULT_TM_KEY ) {

                /*
                 * Update a memory key with the name of th TMX if the key name is empty
                 */
                $mkDao           = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
                $searchMemoryKey = new TmKeyManagement_MemoryKeyStruct();
                $key             = new TmKeyManagement_TmKeyStruct();
                $key->key        = $request[ 'tm_key' ];

                $searchMemoryKey->uid    = $this->user->uid;
                $searchMemoryKey->tm_key = $key;
                $userMemoryKey           = $mkDao->read( $searchMemoryKey );

                if ( empty( $userMemoryKey[ 0 ]->tm_key->name ) && !empty( $userMemoryKey ) ) {
                    $userMemoryKey[ 0 ]->tm_key->name = $fileInfo->name;
                    $mkDao->atomicUpdate( $userMemoryKey[ 0 ] );
                }
            }
        }

        $this->response->json( [
                'errors' => [],
                'data'   => [
                        'uuids' => $uuids
                ]
        ] );

    }

    /**
     * @throws Exception
     */
    public function uploadStatus(): void {

        $request   = $this->validateTheRequest();
        $TMService = new TMSService();
        $status    = $TMService->tmxUploadStatus( $request[ 'uuid' ] );

        $this->response->json( [
                'errors' => [],
                'data'   => $status[ 'data' ],
        ] );

    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array {
        $name   = filter_var( $this->request->param( 'name' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
        $tm_key = filter_var( $this->request->param( 'tm_key' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );
        $uuid   = filter_var( $this->request->param( 'uuid' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW ] );

        if ( empty( $tm_key ) ) {

            if ( empty( INIT::$DEFAULT_TM_KEY ) ) {
                throw new InvalidArgumentException( "Please specify a TM key.", -2 );
            }

            /*
             * Added the default Key.
             * This means if no private key are provided the TMX will be loaded in the default MyMemory key
             */
            $tm_key = INIT::$DEFAULT_TM_KEY;

        }

        return [
                'name'   => $name,
                'tm_key' => $tm_key,
                'uuid'   => $uuid,
        ];
    }
}
