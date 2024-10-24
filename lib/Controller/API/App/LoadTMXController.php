<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Constants_JobStatus;
use Database;
use Exception;
use FilesStorage\AbstractFilesStorage;
use INIT;
use InvalidArgumentException;
use Klein\Response;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyStruct;
use TMS\TMSFile;
use TMS\TMSService;

class LoadTMXController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function newTM(): Response
    {
        try {
            $data = $this->validateTheRequest();
            $TMService = new TMSService();
            $file = $TMService->uploadFile();

            $uuids = [];

            foreach ( $file as $fileInfo ) {

                if ( AbstractFilesStorage::pathinfo_fix( strtolower( $fileInfo->name ), PATHINFO_EXTENSION ) !== 'tmx' ) {
                    throw new Exception( "Please upload a TMX.", -8 );
                }

                $file = new TMSFile(
                    $fileInfo->file_path,
                    $data['tm_key'],
                    $fileInfo->name
                );

                $TMService->addTmxInMyMemory( $file );
                $uuids[] = [ "uuid" => $file->getUuid(), "name" => $file->getName() ];

                $this->featureSet->run( 'postPushTMX', $file, $this->user );

                /*
                 * We update the KeyRing only if this is NOT the Default MyMemory Key
                 *
                 * If it is NOT the default the key belongs to the user, so it's correct to update the user keyring.
                 */
                if ( $data['tm_key'] != INIT::$DEFAULT_TM_KEY ) {

                    /*
                     * Update a memory key with the name of th TMX if the key name is empty
                     */
                    $mkDao           = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
                    $searchMemoryKey = new TmKeyManagement_MemoryKeyStruct();
                    $key             = new TmKeyManagement_TmKeyStruct();
                    $key->key        = $data['tm_key'];

                    $searchMemoryKey->uid    = $this->user->uid;
                    $searchMemoryKey->tm_key = $key;
                    $userMemoryKey           = $mkDao->read( $searchMemoryKey );

                    if ( empty( $userMemoryKey[ 0 ]->tm_key->name ) && !empty( $userMemoryKey ) ) {
                        $userMemoryKey[ 0 ]->tm_key->name = $fileInfo->name;
                        $mkDao->atomicUpdate( $userMemoryKey[ 0 ] );
                    }
                }
            }

            return $this->response->json([
                'errors' => [],
                'data' => [
                    'uuids' => $uuids
                ]
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    public function uploadStatus(): Response
    {
        try {
            $data = $this->validateTheRequest();
            $TMService = new TMSService();
            $status    = $TMService->tmxUploadStatus( $data['uuid'] );

            return $this->response->json([
                'errors' => [],
                'data' => $status[ 'data' ],
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $id = filter_var( $this->request->param( 'res' ), FILTER_SANITIZE_NUMBER_INT);
        $res = filter_var( $this->request->param( 'res' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );
        $new_status = filter_var( $this->request->param( 'new_status' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW  ] );
        $pn = filter_var( $this->request->param( 'pn' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW  ] );

        if ( !Constants_JobStatus::isAllowedStatus( $new_status ) ) {
            throw new Exception( "Invalid Status" );
        }

        return [
            'res_id' => $id,
            'res_type' => $res,
            'password' => $password,
            'new_status' => $new_status,
            'pn' => $pn,
        ];
    }
}
