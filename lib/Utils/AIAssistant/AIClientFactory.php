<?php

namespace Utils\AIAssistant;

use Exception;
use Gemini;
use GuzzleHttp\Client;
use Orhanerday\OpenAi\OpenAi;
use Utils\Registry\AppConfig;

class AIClientFactory
{
    /**
     * @throws Exception
     */
    public static function createAlternativeTranslationsClient(): AlternativeTranslationsClientInterface
    {
        return self::gemini();
    }

    /**
     * @throws Exception
     */
    public static function createTranslationEvaluator(): TranslationEvaluatorClientInterface
    {
        return self::openAi();
    }

    /**
     * @throws Exception
     */
    public static function createContextExplainer(): ContextExplainerClientInterface
    {
        return self::openAi();
    }

    /**
     * @throws Exception
     */
    private static function gemini(): GeminiClient
    {
        if (empty(AppConfig::$GEMINI_API_KEY)) {
            throw new Exception('Gemini API key not set');
        }

        $timeOut = (AppConfig::$GEMINI_TIMEOUT) ?: 30;

        return new GeminiClient(Gemini::factory()
            ->withApiKey(AppConfig::$GEMINI_API_KEY)
            ->withHttpClient(new Client(['timeout' => $timeOut]))
            ->make());
    }

    /**
     * @throws Exception
     */
    private static function openAi(): OpenAIClient
    {
        if (empty(AppConfig::$OPENAI_API_KEY)) {
            throw new Exception('OpenAI API key not set');
        }

        $timeOut = (AppConfig::$OPEN_AI_TIMEOUT) ?: 30;
        $openAi = new OpenAi(AppConfig::$OPENAI_API_KEY);
        $openAi->setTimeout($timeOut);

        return new OpenAIClient($openAi);
    }
}
