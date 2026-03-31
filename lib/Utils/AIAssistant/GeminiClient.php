<?php

namespace Utils\AIAssistant;

use Gemini\Contracts\ClientContract;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Gemini\Enums\ResponseMimeType;

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
        string $sourceLanguage,
        string $targetLanguage,
        string $sourceSentence,
        string $sourceContextSentencesString,
        string $targetSentence,
        string $targetContextSentencesString,
        string $excerpt,
        string $styleInstructions
    ): array {
        // Tag Protection Mechanism:
        // Identify all tags in the sentences and replace them with opaque placeholders.
        // This prevents the AI from attempting to parse or "fix" HTML tags.
        $tagMap = [];
        $maskTags = function ( ?string $text ) use ( &$tagMap ) {
            if ( empty( $text ) ) {
                return $text;
            }

            return preg_replace_callback( '/<(?:[^"\'>]|"[^"]*"|\'[^\']*\')*>/u', function ( $matches ) use ( &$tagMap ) {
                $tag = $matches[ 0 ];
                $placeholder = array_search( $tag, $tagMap );
                if ( $placeholder === false ) {
                    $placeholder = "[[T" . count( $tagMap ) . "]]";
                    $tagMap[ $placeholder ] = $tag;
                }

                return $placeholder;
            }, $text );
        };

        $mSourceSentence = $maskTags( $sourceSentence );
        $mSourceContext = $maskTags( $sourceContextSentencesString );
        $mTargetSentence = $maskTags( $targetSentence );
        $mTargetContext = $maskTags( $targetContextSentencesString );
        $mExcerpt = $maskTags( $excerpt );

        $prompt = <<<PROMPT
You are an expert $sourceLanguage to $targetLanguage translator.

  Given:
   - Source sentence:
     """
     {$mSourceSentence}
     """


   - Source sentence context:
     """
     {$mSourceContext}
     """


   - Target sentence:
     """
     {$mTargetSentence}
     """


   - Target sentence context:
     """
     {$mTargetContext}
     """


   - Target excerpt to be replaced:
     """
     {$mExcerpt}
     """
    
   - Suggest up to 4 alternative translations in $targetLanguage that replace the exact text "$mExcerpt" in the "Target sentence" provided above.
   - You MUST return the full new target sentence, with the excerpt replaced, in the "alternative" field.
   - Every character of the "Target sentence" that is NOT part of the "$mExcerpt" MUST remain exactly as it is, including all placeholders, spaces, and punctuation.

 *Token Integrity Protocol*
   - Tokens like [[T0]], [[T1]], etc., are mandatory placeholders representing non-translatable tags.
   - Treat tokens as atomic, immutable constants.
   - You MUST NOT translate, modify, or remove these tokens.
   - You MUST NOT add any content between tokens if they were adjacent in the original sentence.
   - You MUST NOT wrap any text with these tokens.
   - Copy every token character-by-character from the original sentence into the alternative.

 *Instructions to generate alternative translations*
   - Always ensure that only the specified excerpt is altered, and all other parts of the sentence remain unchanged unless absolutely necessary for grammatical correctness with the new excerpt.
   - Golden Rule: If "$mExcerpt" has no meaning in the $targetLanguage, return an empty JSON.
  
   {$this->style($styleInstructions, $targetLanguage)}
  
   - *For each alternative translation proposal*:
     - *Golden rule*: always Return the full new target sentence in "alternative" schema field
     - Never suggest the current translation or selected excerpt as an alternative.
     - At least the excerpt to replace should be replaced. If not, do not propose alternatives.
     - Do not contain archaic or unnatural terms (e.g. on the morrow)
     - Must be grammatically correct.
     - When the source sentence allows it and no precise contextual info about it is given, propose gender alternatives including neutral.
     - Return a short context (max 8 words), written entirely in $targetLanguage, that explains when the alternative should be used only in terms of translation context, mood or style.
     - Context must be understandable in $targetLanguage
     - Make sure it contains all not changed terms of the original target sentence
  
   - *Golden rule*: If "$mExcerpt" is a proper noun, a brand, or part of it, return an empty JSON.
   - *Golden rule* if you don't have any reasonable alternative to suggest it's ok to return an empty JSON.
PROMPT;

        $model = ( AppConfig::$GEMINI_API_MODEL ) ?: 'gemini-2.5-flash-lite';

        $generationConfig = new GenerationConfig(
            temperature: 0.3,
            responseMimeType: ResponseMimeType::APPLICATION_JSON,
            responseSchema: new Schema(
                type: DataType::ARRAY,
                items: new Schema(
                    type: DataType::OBJECT,
                    properties: [
                        'alternative' => new Schema(DataType::STRING),
                        'context' => new Schema(DataType::STRING)
                    ]
                )
            )
        );

        $result = $this->gemini
            ->generativeModel(model: $model)
            ->withGenerationConfig($generationConfig)
            ->generateContent($prompt)->json();

        // Restore tags:
        if ( is_array( $result ) && !empty( $tagMap ) ) {
            foreach ( $result as &$proposal ) {
                if ( isset( $proposal->alternative ) ) {
                    $proposal->alternative = str_replace( array_keys( $tagMap ), array_values( $tagMap ), $proposal->alternative );
                }
                if ( isset( $proposal->context ) ) {
                    $proposal->context = str_replace( array_keys( $tagMap ), array_values( $tagMap ), $proposal->context );
                }
            }
        }

        return $result;
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
}
