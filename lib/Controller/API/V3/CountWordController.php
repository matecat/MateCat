<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace API\V3;

use API\V2\Exceptions\ValidationError;
use API\V2\KleinController;
use CatUtils;
use Langs_Languages;
use LQA\SizeRestriction;


class CountWordController extends KleinController {

    protected $language;

    protected function afterConstruct() {

        $this->language = !empty( $this->request->language ) ? $this->request->language : 'en-US';

        if ( $this->request->text === null or $this->request->text === '') {
            throw new ValidationError( "Invalid text field", 400 );
        }

        if ( $this->request->limit === null or $this->request->limit === '') {
            throw new ValidationError( "Invalid limit field", 400 );
        }

        $langs = Langs_Languages::getInstance();

        try {
            $langs->validateLanguage( $this->language );
        } catch ( \Exception $e ) {
            throw new ValidationError( $e->getMessage(), 400 );
        }

    }

    public function rawWords() {
        $words_count = CatUtils::segment_raw_word_count( $this->request->text, $this->language );
        $size_restriction = new SizeRestriction($this->request->text, $this->request->limit);

        $this->response->json( [
            'word_count' => $words_count,
            'character_count' => [
                'valid' => $size_restriction->checkLimit(),
                'length' => $size_restriction->getCleanedStringLength(),
                'remaining_characters' => $size_restriction->getCharactersRemaining(),
            ],
        ] );
    }
}