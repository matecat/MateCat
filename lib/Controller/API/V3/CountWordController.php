<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace API\V3;

use API\V2\KleinController;
use CatUtils;
use Langs_Languages;


class CountWordController extends KleinController {

    protected function afterConstruct() {
        $this->language = !empty( $this->request->language ) ? $this->request->language : 'en-US';
        if ( empty( $this->request->string ) ) {
            $this->result[ 'errors' ][] = [ "code" => -1, "message" => "String field must to be sent" ];
        }

        $langs = Langs_Languages::getInstance();

        try {
            $langs->validateLanguage( $this->language );
        } catch ( \Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => -2, "message" => $e->getMessage() ];
        }

        if ( !empty( $this->result[ 'errors' ] ) ) {
            $this->response->json( $this->result );

            return;
        }
    }

    public function wordsCount() {
        $words_count                   = CatUtils::segment_raw_word_count( $this->request->string, $this->language );
        $this->result[ 'words_count' ] = $words_count;
        $this->response->json( $this->result );
    }
}