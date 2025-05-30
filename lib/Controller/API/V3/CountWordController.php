<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace API\V3;

use AbstractControllers\KleinController;
use API\Commons\Exceptions\ValidationError;
use API\Commons\Validators\LoginValidator;
use CatUtils;
use Exception;
use Langs\Languages;
use LQA\SizeRestriction\SizeRestriction;
use Matecat\SubFiltering\MateCatFilter;


class CountWordController extends KleinController {

    protected string $language;

    /**
     * @throws ValidationError
     */
    protected function afterConstruct() {

        $this->language = $this->request->param( 'language' ) ?: 'en-US';

        if ( $this->request->param( 'text' ) === null or $this->request->param( 'text' ) === '') {
            throw new ValidationError( "Invalid text field", 400 );
        }

        $langs = Languages::getInstance();

        try {
            $langs->validateLanguage( $this->language );
        } catch ( Exception $e ) {
            throw new ValidationError( $e->getMessage(), 400, $e );
        }

        $this->appendValidator( new LoginValidator( $this ) );

    }

    public function rawWords() {

        $this->featureSet->loadFromUserEmail( $this->user->email );
        $words_count = CatUtils::segment_raw_word_count( $this->request->param( 'text' ), $this->language );
        $filter = MateCatFilter::getInstance($this->featureSet);
        $size_restriction = new SizeRestriction($filter->fromLayer0ToLayer2($this->request->param( 'text' )), $this->featureSet);

        $character_count = [
            'length' =>  $size_restriction->getCleanedStringLength(),
        ];

        if(isset($this->request->limit) and is_numeric($this->request->limit)){
            $character_count['valid'] = $size_restriction->checkLimit($this->request->limit);
            $character_count['remaining_characters'] = $size_restriction->getCharactersRemaining($this->request->limit);
        }

        $this->response->json( [
            'word_count' => $words_count,
            'character_count' => $character_count,
        ] );
    }
}