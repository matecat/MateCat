<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\DataAccess\Database;
use Model\FilesStorage\AbstractFilesStorage;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Utils\Registry\AppConfig;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\TMS\TMSFile;
use Utils\TMS\TMSService;

class TMXFileController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function import(): void
    {
        $request   = $this->validateTheRequest();
        $TMService = new TMSService();
        $file      = $TMService->uploadFile();

        $uuids = [];

        foreach ($file as $fileInfo) {
            if (AbstractFilesStorage::pathinfo_fix(strtolower($fileInfo->name), PATHINFO_EXTENSION) !== 'tmx') {
                throw new Exception("Please upload a TMX.", -8);
            }

            $file = new TMSFile(
                    $fileInfo->file_path,
                    $request[ 'tm_key' ],
                    $fileInfo->name
            );

            $TMService->addTmxInMyMemory($file, $this->user);
            $uuids[] = ["uuid" => $file->getUuid(), "name" => $file->getName()];

            $this->featureSet->run('postPushTMX', $file, $this->user);

            /*
             * We update the KeyRing only if this is NOT the Default MyMemory Key
             *
             * If it is NOT the default the key belongs to the user, so it's correct to update the user keyring.
             */
            if ($request[ 'tm_key' ] != AppConfig::$DEFAULT_TM_KEY) {
                /*
                 * Update a memory key with the name of th TMX if the key name is empty
                 */
                $mkDao           = new MemoryKeyDao(Database::obtain());
                $searchMemoryKey = new MemoryKeyStruct();
                $key             = new TmKeyStruct();
                $key->key        = $request[ 'tm_key' ];

                $searchMemoryKey->uid    = $this->user->uid;
                $searchMemoryKey->tm_key = $key;
                $userMemoryKey           = $mkDao->read($searchMemoryKey);

                if (empty($userMemoryKey[ 0 ]->tm_key->name) && !empty($userMemoryKey)) {
                    $userMemoryKey[ 0 ]->tm_key->name = $fileInfo->name;
                    $mkDao->atomicUpdate($userMemoryKey[ 0 ]);
                }
            }
        }

        $this->response->json([
                'errors' => [],
                'data'   => [
                        'uuids' => $uuids
                ]
        ]);
    }

    /**
     * @throws Exception
     */
    public function importStatus(): void
    {
        $uuid      = filter_var($this->request->param('uuid'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);
        $TMService = new TMSService();
        $status    = $TMService->tmxUploadStatus($uuid);

        $this->response->json([
                'errors' => [],
                'data'   => $status[ 'data' ],
        ]);
    }

    /**
     * @return array
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $name   = filter_var($this->request->param('name'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $tm_key = filter_var($this->request->param('tm_key'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);
        $uuid   = filter_var($this->request->param('uuid'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW]);

        if (empty($tm_key)) {
            if (empty(AppConfig::$DEFAULT_TM_KEY)) {
                throw new InvalidArgumentException("Please specify a TM key.", -2);
            }

            /*
             * Added the default Key.
             * This means if no private key are provided the TMX will be loaded in the default MyMemory key
             */
            $tm_key = AppConfig::$DEFAULT_TM_KEY;
        }

        return [
                'name'   => $name,
                'tm_key' => $tm_key,
                'uuid'   => $uuid,
        ];
    }
}
