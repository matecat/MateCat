<?php


namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Utils\Registry\AppConfig;

class SupportedFilesController extends KleinController
{


    public function index(): void
    {
        $this->response->json(
                $this->getFileList()
        );
    }

    /**
     * @return array
     */
    private function getFileList(): array
    {
        $ret = [];

        foreach (AppConfig::$SUPPORTED_FILE_TYPES as $key => $value) {
            $val = [];
            foreach ($value as $ext => $info) {
                $val[] = [
                        'ext'   => $ext,
                        'class' => $info[ 2 ]
                ];
            }

            $val         = array_chunk($val, 1);
            $ret[ $key ] = $val;
        }

        return $ret;
    }
}