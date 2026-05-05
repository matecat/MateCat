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
    public function evaluateTranslation(
        string $sourceLanguage,
        string $targetLanguage,
        string $text,
        string $translation,
        string $style
    ): bool|array
    {
        $promptTemplate = "You are Lara, the world's most trustworthy translation reviewer.
Your task is to evaluate a user-edited translation from {sourceLanguage} to {targetLanguage}, based on the original source text. Use the definitions below to classify the edited translation into one of the four categories. Your evaluation should be concise, fair, and constructive.

# Categories:
- **Excellent**: The edited translation is accurate, natural, and contextually appropriate. No changes are needed.
- **Good**: The edit captures the general meaning and tone but could benefit from small refinements in style, clarity, or nuance.
- **Could Be Improved**: The translation contains noticeable issues — such as awkward phrasing, partial misunderstandings, or missing details — that affect accuracy or quality. However, it still broadly corresponds to the source. Misspellings are always in this category.
- **Does Not Match Source** : The translation diverges from the meaning of the source text. This includes introducing concepts, facts, or interpretations that are not present or implied in the original. The two do not correspond and a full rewrite is needed.
  
# Assessment Guidelines:
- You are talking to the user: maintain a friendly, approachable, and human-like tone. Be always kind.
- Provide transparent, reliable, empathetic, and credible feedback

ALWAYS provide your feedback in English, except for any suggestions of changes to apply to the translation, that MUST be in {targetLanguage} for obvious reasons.
NEVER EVER translate the category.

Your output must always be:
category
comment

Now evaluate the following:

Source: {text}

Edited Translation: {translation}

Return your classification and a brief explanation (2–3 lines) in JSON format.";

        // Replace the placeholders with actual variables
        $vars = [
            '{sourceLanguage}' => $sourceLanguage,
            '{targetLanguage}' => $targetLanguage,
            '{text}'           => $text,
            '{translation}'    => $translation,
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
            "response_format" => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'feedback_struct',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'category' => [
                                'type' => 'string',
                            ],
                            'comment' => [
                                'type' => 'string',
                            ],
                        ],
                        'required' => ['category','comment'],
                        'additionalProperties' => false,
                    ],
                ]
            ],
        ];

        $response = $this->openAi->chat($opts);
        $response = json_decode($response, true);

        $message = $response['choices'][0]['message']['content'];
        $feedback = json_decode($message);

        return [
            'category' => $this->formatFeedbackString($feedback->category),
            'comment' => $this->formatFeedbackString($feedback->comment),
        ];
    }

    /**
     * Formats the input feedback string by replacing specific characters and trimming whitespace.
     *
     * @param string $string The input feedback string to be formatted.
     * @return string The formatted feedback string.
     */
    protected function formatFeedbackString(string $string): string
    {
        $string = str_replace("-", " ", $string);
        $string = str_replace("*", " ", $string);

        return trim($string);
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
