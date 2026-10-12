<?php


namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Utils\Registry\AppConfig;

class SupportedFilesController extends KleinController
{


    /**
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    public function index(): void
    {
        $this->response->json(
            $this->getFileList()
        );
    }

    /**
     * @return array<string, list<list<array{ext: string, class: string}>>>
     */
    private function getFileList(): array
    {
        $ret = [];

        foreach (AppConfig::$SUPPORTED_FILE_TYPES as $key => $value) {
            $val = [];
            foreach ($value as $ext => $info) {
                $val[] = [
                    'ext' => $info['label'] ?? $ext,
                    'class' => $info['class']
                ];
            }

            $val = array_chunk($val, 1);
            $ret[$key] = $val;
        }

        return $ret;
    }
}