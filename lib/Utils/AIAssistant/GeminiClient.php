<?php

namespace Utils\AIAssistant;

use Gemini\Contracts\ClientContract;
use Gemini\Data\GenerationConfig;
use Utils\Registry\AppConfig;

class GeminiClient implements AIClientInterface
{
    private ClientContract $gemini;

    public function __construct(ClientContract $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Manages the generation of alternative translations for a specific excerpt within a target sentence.
     *
     * @param string $sourceLanguage The source language of the translation.
     * @param string $targetLanguage The target language of the translation.
     * @param string $sourceSentence The original source sentence to be translated.
     * @param string $sourceContextSentencesString Additional context sentences related to the source sentence.
     * @param string $targetSentence The current translated target sentence.
     * @param string $targetContextSentencesString Additional context sentences related to the target translation.
     * @param string $excerpt The specific excerpt in the target sentence to be replaced with alternatives.
     * @param string $styleInstructions The style instructions provided to guide the translation approach.
     * @return mixed The formatted response containing alternative translations, or nothing if no alternatives can be reasonably suggested.
     */
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
    - Golden Rule: If "{$excerpt}" has no meaning in {$targetLanguage}, return nothing.
    
    {$this->style($styleInstructions, $targetLanguage)}

    - *For each alternative translation proposal*:
      - ALWAYS ANSWER IN JSON FORMAT
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

        $generationConfig = new GenerationConfig(
            temperature: 0.3
        );

        $result = $this
            ->gemini
            ->generativeModel(model: AppConfig::$GEMINI_API_MODEL)
            ->withGenerationConfig($generationConfig)
            ->generateContent($prompt)
        ;
        $text = $result->text();

        return $this->formatResponse($text);
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
- Alternatives can include idioms, cultural substitutions, or reimagining — especially for effect or engagement.
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
    private function formatResponse($response)
    {
        if(!is_string($response)){
            return $response;
        }

        if(!str_contains($response, "```json")){
            return $response;
        }

        // decode JSON
        $alternatives = str_replace(["```json", "```"], "", $response);

        return json_decode($alternatives, true);
    }
}
