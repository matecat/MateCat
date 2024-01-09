<?php


namespace API\App;


use API\V2\KleinController;
use INIT;

class SupportedFilesController extends KleinController {


    public function index()
    {
        $this->response->json(
            $this->getFileList()
        );
    }

    /**
     * @return array
     */
    private function getFileList()
    {
        $ret = [];

        foreach ( INIT::$SUPPORTED_FILE_TYPES as $key => $value ) {
            $val = [];
            foreach ( $value as $ext => $info ) {
                $val[] = [
                    'ext'   => $ext,
                    'class' => $info[ 2 ]
                ];
            }

            $val = array_chunk( $val, 1 );
            $ret[ $key ] = $val;
        }

        return $ret;
    }
}