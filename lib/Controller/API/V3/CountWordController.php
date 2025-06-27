<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace API\V3;

use CatUtils;
use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Langs\Languages;
use Matecat\SubFiltering\MateCatFilter;
use Utils\LQA\SizeRestriction\SizeRestriction;


class CountWordController extends KleinController {

    protected string $language;

    /**
     * @throws ValidationError
     */
    protected function afterConstruct() {

        $this->language = $this->request->param( 'language' ) ?: 'en-US';

        if ( $this->request->param( 'text' ) === null or $this->request->param( 'text' ) === '' ) {
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

    /**
     * @throws Exception
     */
    public function rawWords() {

        $this->featureSet->loadFromUserEmail( $this->user->email );
        $words_count = CatUtils::segment_raw_word_count( $this->request->param( 'text' ), $this->language );
        $filter      = MateCatFilter::getInstance( $this->featureSet );
        /** @var $filter MateCatFilter */
        $size_restriction = new SizeRestriction( $filter->fromLayer0ToLayer2( $this->request->param( 'text' ) ), $this->featureSet );

        $character_count = [
                'length' => $size_restriction->getCleanedStringLength(),
        ];

        if ( isset( $this->request->limit ) and is_numeric( $this->request->limit ) ) {
            $character_count[ 'valid' ]                = $size_restriction->checkLimit( $this->request->limit );
            $character_count[ 'remaining_characters' ] = $size_restriction->getCharactersRemaining( $this->request->limit );
        }

        $this->response->json( [
                'word_count'      => $words_count,
                'character_count' => $character_count,
        ] );
    }
}