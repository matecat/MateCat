<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 08/02/2019
 * Time: 13:03
 */

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Matecat\Locales\Languages;
use Matecat\SubFiltering\MateCatFilter;
use RuntimeException;
use Utils\LQA\SizeRestriction\SizeRestriction;
use Utils\Tools\CatUtils;


class CountWordController extends KleinController
{

    protected string $language;

    /**
     * @throws ValidationError
     * @throws \TypeError
     */
    protected function registerValidators(): void
    {
        $this->language = $this->request->param('language') ?: 'en-US';

        if ($this->request->param('text') === null or $this->request->param('text') === '') {
            throw new ValidationError("Invalid text field", 400);
        }

        $langs = Languages::getInstance();

        try {
            $langs->validateLanguage($this->language);
        } catch (Exception $e) {
            throw new ValidationError($e->getMessage(), 400, $e);
        }

        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    protected function getRawWordsCount(string $text, string $language): int
    {
        return (new CatUtils($this->getDatabase()))->countSegmentRawWords($text, $language);
    }

    /**
     * @throws Exception
     */
    protected function buildSizeRestriction(string $text): SizeRestriction
    {
        $filter = MateCatFilter::getInstance($this->featureSet);
        if (!$filter instanceof MateCatFilter) {
            throw new RuntimeException('Expected MateCatFilter instance from getInstance()');
        }

        return new SizeRestriction($filter->fromLayer0ToLayer2($text), $this->featureSet);
    }

    /**
     * @throws Exception
     */
    public function rawWords(): void
    {
        $this->featureSet->loadFromUserEmail($this->user->email ?? '');
        $words_count = $this->getRawWordsCount($this->request->param('text'), $this->language);
        $size_restriction = $this->buildSizeRestriction($this->request->param('text'));

        $character_count = [
            'length' => $size_restriction->getCleanedStringLength(),
        ];

        if (isset($this->request->limit) and is_numeric($this->request->limit)) {
            $character_count['valid'] = $size_restriction->checkLimit((int)$this->request->limit);
            $character_count['remaining_characters'] = $size_restriction->getCharactersRemaining((int)$this->request->limit);
        }

        $this->response->json([
            'word_count' => $words_count,
            'character_count' => $character_count,
        ]);
    }
}