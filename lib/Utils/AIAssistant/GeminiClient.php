<?php

namespace Utils\AIAssistant;

use Gemini\Contracts\ClientContract;
use IntlBreakIterator;
use Utils\Registry\AppConfig;

class GeminiClient implements AIClientInterface
{
    private ClientContract $gemini;

    public function __construct(ClientContract $gemini)
    {
        $this->gemini = $gemini;
    }

    public function manageAlternativeTranslations(
        $sourceLanguage,
        $targetLanguage,
        $sourceSentence,
        $sourceContextSentencesString,
        $targetSentence,
        $targetContextSentencesString,
        $excerpt,
        $styleInstructions
    )
    {
        $prompt = <<<PROMPT
You are an expert {$sourceLanguage} to {$targetLanguage} translator.

  Given:
    - Source sentence:
      """
      {$sourceSentence}
      """

    - Source sentence context:
      """
      {$sourceContextSentencesString}
      """

    - Target sentence:
      """
      {$targetSentence}
      """

    - Target sentence context:
      """
      {$targetContextSentencesString}
      """

    - Target excerpt to be replaced:
      """
      {$excerpt}
      """


  Suggest up to 4 alternative translations in {$targetLanguage} that replaces "{$excerpt}" in the target sentence.

  *Instructions to generate alternative translations*
    - Always ensure that only the specified excerpt is altered, and all other parts of the sentence remain unchanged unless absolutely necessary for grammatical correctness with the new excerpt.
    - Golden Rule: If "{$excerpt}" has no meaning in the {$targetLanguage}, return nothing.
    
    {$this->style($styleInstructions, $targetLanguage)}

    - *For each alternative translation proposal*:
      - *Golden rule*: always Return the full new target sentence in "alternative" schema field
      - Never suggest the current translation or selected excerpt as an alternative.
      - At least the excerpt to replace should be replaced. If not, do not propose alternative.
      - Do not contain archaic or unnatural terms (e.g. on the morrow)
      - Must be grammatically correct.
      - When the source sentence allows it and no given precise contextual info about it, propose gender alternatives including neutral.
      - Return a short context (max 8 words), written entirely in {$targetLanguage}, that explains when the alternative should be used only in terms of translation context, mood or style.
      - Context must be understandable in {$targetLanguage} and the corresponding json key should always be called “context”
      - Make sure it contains all not changed terms of the original target sentence
    
    - *Golden rule*: If "{$excerpt}" is proper noun, a brand, or part of it, return nothing.
    - *Golden rule* if you don't have any reasonable alternative to suggest it's ok to return nothing.
PROMPT;

        $result = $this->gemini->generativeModel(model: AppConfig::$GEMINI_API_MODEL)->generateContent($prompt);
        $text = $result->text();

        return $this->formatResponse($targetLanguage, $targetSentence, $text);
    }

    /**
     * Generates style instructions based on the provided translation style and target language.
     *
     * @param string $style The translation style, which can be 'faithful', 'fluid', or 'creative'.
     * @param string $targetLanguage The target language for the translation instructions.
     * @return string The style instructions corresponding to the provided style and target language.
     */
    private function style(string $style, string $targetLanguage): string
    {
        $styleInstructionsMap = [
            'faithful' => '
- Proposed alternative translation must be a literal and faithful translation of the source sentence.
- Focus on accuracy and fidelity to the source text.
',
            'fluid' => '
- Ensure the sentence flows naturally in the target language, even if small shifts in structure or idiom are needed.
- Use alternatives that sound native while retaining the core meaning of the original.
- Balance clarity with fidelity — prioritize reader comprehension in '.$targetLanguage.'.            
',
            'creative' => '
- You may adapt, rephrase, or take stylistic liberties, as long as the spirit and function of the original are respected.
- Alternatives can include idioms, cultural substitutions, or reimaginings — especially for effect or engagement.
- Be creative.    
',
        ];

        return $styleInstructionsMap[$style] ?? '';
    }

    /**
     * Formats the response from the Gemini API into a more usable format.
     *
     * @return array|string
     */
    private function formatResponse($targetLanguage, $originalSentence, $response)
    {
        if(!is_string($response)){
            return $response;
        }

        if(!str_contains($response, "```json")){
            return $response;
        }

        // decode JSON
        $alternatives = str_replace(["```json", "```"], "", $response);
        $alternatives = json_decode($alternatives, true);

        return $this->enrichAlternatives($targetLanguage, $originalSentence, $alternatives);
    }

    /**
     * Enhances a list of alternative translations by providing additional context, word differences,
     * and restored formatting based on the original sentence.
     *
     * @param string $targetLanguage The target language code, used to determine the word segmentation logic.
     * @param string $originalSentence The original sentence for which alternatives are being enriched.
     * @param array $alternatives A list of alternative translations. Each alternative is expected to be an
     *                                     associative array with keys such as 'alternative' and 'context'.
     * @param int $contextWindowSize The number of words to include before and after the modified section for
     *                                     contextual inclusion, defaults to 3.
     *
     * @return array A transformed array of alternatives, where each alternative includes the enriched 'alternative' text,
     *               highlighted context with 'before', 'changed', and 'after' segments, 'context' metadata, the
     *               detected 'original' and 'replacement' word differences, and restored formatting from the original
     *               sentence.
     */
    private function enrichAlternatives(
        string $targetLanguage,
        string $originalSentence,
        array  $alternatives,
        int    $contextWindowSize = 3
    ): array {
        $originalWords = $this->splitWords($targetLanguage, $originalSentence);

        return array_map(function (array $item) use ($targetLanguage, $originalSentence, $originalWords, $contextWindowSize) {

            $alternative = $this->restoreMissingWhiteSpace($originalSentence, $item['alternative']);
            // Restore trailing newline from original sentence
            if (str_ends_with($originalSentence, "\n") && !str_ends_with($alternative, "\n")) {
                $alternative .= "\n";
            }
            $modifiedWords = $this->splitWords($targetLanguage, $alternative);
            $context = $item['context'];

            $modifiedWordsRange = $this->getModifiedWordsRange($originalWords, $modifiedWords);
            $startModified = $modifiedWordsRange['startModified'];
            $endModified = $modifiedWordsRange['endModified'];
            $endOriginal = $modifiedWordsRange['endOriginal'];

            $changed = array_slice($modifiedWords, $startModified, $endModified - $startModified + 1);
            $before  = array_slice($modifiedWords, max(0, $startModified - $contextWindowSize), min($startModified, $contextWindowSize));
            $after   = array_slice($modifiedWords, $endModified + 1, $contextWindowSize);

            $originalDiff    = implode(' ', array_slice($originalWords, $startModified, $endOriginal - $startModified + 1));
            $replacementDiff = implode(' ', $changed);

            $hasStartEllipsis = ($startModified - $contextWindowSize) > 0;
            $hasEndEllipsis   = ($endModified + 1 + $contextWindowSize) < count($modifiedWords);

            return [
                'alternative' => $alternative,
                'highlighted' => [
                    'before'  => $hasStartEllipsis ? ' ...' . implode(' ', $before) : implode(' ', $before),
                    'changed' => $replacementDiff,
                    'after'   => $hasEndEllipsis   ? implode(' ', $after) . '... '  : implode(' ', $after),
                ],
                'context'     => $context,
                'original'    => $originalDiff,
                'replacement' => $replacementDiff,
            ];

        }, $alternatives);
    }

    /**
     * Splits a given text into an array of words based on the specified language code.
     *
     * @param string $languageCode The language code to determine the word segmentation logic. Certain languages
     *                              such as Thai, Chinese, Japanese, and Traditional Chinese handle word splitting
     *                              differently due to the lack of spaces between words.
     * @param string $text The text to be split into words.
     *
     * @return array An array of words obtained from splitting the input text. Empty segments and non-word characters
     *               are excluded from the result.
     */
    private function splitWords(string $languageCode, string $text): array
    {
        $noSpaceLanguages = ['th', 'zh-CN', 'zh-TW', 'ja'];

        if (in_array($languageCode, $noSpaceLanguages)) {
            // IntlBreakIterator is the PHP equivalent of Intl.Segmenter
            $iterator = IntlBreakIterator::createWordInstance($languageCode);
            $iterator->setText($text);

            $words = [];
            $parts = $iterator->getPartsIterator();

            foreach ($parts as $part) {
                $trimmed = trim($part);
                // Filter out empty segments and punctuation/spaces
                if ($trimmed !== '' && preg_match('/\p{L}/u', $trimmed)) {
                    $words[] = $trimmed;
                }
            }

            return $words;
        }

        // Standard whitespace split for other languages
        return preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Restores missing trailing whitespace in the alternative text based on the original text.
     *
     * @param string $original The original text used as a reference for whitespace comparison.
     * @param string $alternative The alternative text to be checked and potentially modified.
     *
     * @return string The alternative text with restored trailing whitespace, if it was missing
     *                while present in the original text; otherwise, the alternative text as is.
     */
    private function restoreMissingWhiteSpace(string $original, string $alternative)
    {
        if (str_ends_with($original, " ") && !str_ends_with($alternative, " ")) {
            return $alternative . " ";
        }

        return $alternative;
    }

    /**
     * Identifies the range of modified words between an original sentence and an alternative sentence.
     *
     * @param array $originalSentenceWords The original sentence splittd into words.
     * @param array $alternativeSentenceWords The alternative sentence split into words.
     * @return array
     */
    private function getModifiedWordsRange(array $originalSentenceWords, array $alternativeSentenceWords)
    {
        $startModified = 0;

        $originalCount    = count($originalSentenceWords);
        $alternativeCount = count($alternativeSentenceWords);

        while (
            $startModified < $originalCount &&
            $startModified < $alternativeCount &&
            $originalSentenceWords[$startModified] === $alternativeSentenceWords[$startModified]
        ) {
            $startModified++;
        }

        $endOriginal = $originalCount - 1;
        $endModified = $alternativeCount - 1;

        while (
            $endOriginal >= $startModified &&
            $endModified >= $startModified &&
            $originalSentenceWords[$endOriginal] === $alternativeSentenceWords[$endModified]
        ) {
            $endOriginal--;
            $endModified--;
        }

        return [
            'startModified' => $startModified,
            'endModified' => $endModified,
            'endOriginal' => $endOriginal
        ];
    }
}
