<?php

namespace Utils\AIAssistant;

use Exception;
use Orhanerday\OpenAi\OpenAi;
use Utils\Registry\AppConfig;

class OpenAIClient implements AIClientInterface
{
    /**
     * @var OpenAi
     */
    private OpenAi $openAi;

    /**
     * Client constructor.
     *
     * @param OpenAi $openAi
     */
    public function __construct(OpenAi $openAi)
    {
        $this->openAi = $openAi;
    }

    /**
     * @throws Exception
     */
    public function evaluateTranslation($sourceLanguage, $targetLanguage, $text, $translation, $context, $style): bool|string
    {
        $promptTemplate = "You are Lara, the world's most trustworthy translation reviewer.
Your task is to evaluate a user-edited translation from {sourceLanguage} to {targetLanguage}, based on the original source text. Use the definitions below to classify the edited translation into one of the four categories. Your evaluation should be concise, fair, and constructive.

# Categories:
- **Excellent**: The edited translation is accurate, natural, and contextually appropriate. No changes are needed.
  - If the translation style is \"Faithful\", all meaningful elements of the source must be preserved. Omitting words, phrases, or structural components without justification disqualifies the translation from this category.
- **Good**: The edit captures the general meaning and tone but could benefit from small refinements in style, clarity, or nuance.
- **Could Be Improved**: The translation contains noticeable issues — such as awkward phrasing, partial misunderstandings, or missing details — that affect accuracy or quality. However, it still broadly corresponds to the source. Misspellings are always in this category.
- **Does Not Match Source** : The translation diverges from the meaning of the source text. This includes introducing concepts, facts, or interpretations that are not present or implied in the original. The two do not correspond and a full rewrite is needed.
  - If style is \"Faithful\" and translation is missing words then this category should be applied.

# Translation Styles Definitions:
- Faithful: Focus on accuracy and fidelity to the source text, including structure, vocabulary, and stylistic elements. Your feedback should address how well the translation preserves the original's precise meaning, tone, and nuances.
- Fluid: Prioritize readability and natural flow in the target language. Consider how effectively the translation conveys the core meaning while maintaining a natural tone. Feedback should focus on the balance between fidelity and fluidity.
- Creative: This mode allows for artistic liberties, including adapting cultural references, adding elements, or reimagining the text. Assess how well the translation captures the spirit of the original while creating an engaging text in the target language.

# Assessment Guidelines:
- You are talking to the user: maintain a friendly, approachable, and human-like tone. Be always kind.
- Provide transparent, reliable, empathetic, and credible feedback
- When you mention the style name in your feedback, always exclusively use its translation in {targetLanguage} and no other language.
- For creative style it is OK to introduce words that have no match in the source text, so source and translation are not aligned.

ALWAYS provide your feedback in {targetLanguage} with exclusion of category that MUST be in English.
NEVER EVER translate the category.

Your output must always be:
<category>
<comment>

Now evaluate the following:

Source: {text}
Edited Translation: {translation}
context: {context}
style: {style}

Return your classification and a brief explanation (2–3 lines).";

        // Replace the placeholders with actual variables
        $vars = [
            '{sourceLanguage}' => $sourceLanguage,
            '{targetLanguage}' => $targetLanguage,
            '{text}'           => $text,
            '{translation}'    => $translation,
            '{context}'        => $context,
            '{style}'          => $style
        ];

        $prompt = strtr($promptTemplate, $vars);
        $model = (AppConfig::$OPEN_AI_MODEL and AppConfig::$OPEN_AI_MODEL !== '') ? AppConfig::$OPEN_AI_MODEL : 'gpt-3.5-turbo';
        $maxTokens = (AppConfig::$OPEN_AI_MAX_TOKENS and AppConfig::$OPEN_AI_MAX_TOKENS !== '') ? (int)AppConfig::$OPEN_AI_MAX_TOKENS : 500;
        $realMaxTokens = (4000 - $maxTokens);

        $opts = [
            'model' => $model,
            'messages' => [
                [
                    "role" => "user",
                    "content" => $prompt
                ],
            ],
            'temperature' => 1.0,
            'max_tokens' => $realMaxTokens,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            "stream" => false,
        ];

        return $this->openAi->chat($opts);
    }

    /**
     * @param          $word
     * @param          $phrase
     * @param          $target
     * @param callable $callback
     *
     * @return void
     * @throws Exception
     */
    public function findContextForAWord($word, $phrase, $target, callable $callback): void
    {
        $phrase = strip_tags($phrase);
        $content = "Explain, in " . $target . ", the meaning of '" . $word . "' when used in this context : '" . $phrase . "'";
        $model = (AppConfig::$OPEN_AI_MODEL and AppConfig::$OPEN_AI_MODEL !== '') ? AppConfig::$OPEN_AI_MODEL : 'gpt-3.5-turbo';
        $maxTokens = (AppConfig::$OPEN_AI_MAX_TOKENS and AppConfig::$OPEN_AI_MAX_TOKENS !== '') ? (int)AppConfig::$OPEN_AI_MAX_TOKENS : 500;
        $realMaxTokens = (4000 - $maxTokens);

        $opts = [
            'model' => $model,
            'messages' => [
                [
                    "role" => "user",
                    "content" => $content
                ],
            ],
            'temperature' => 1.0,
            'max_tokens' => $realMaxTokens,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            "stream" => true,
        ];

        $this->openAi->chat($opts, $callback);
    }
}
