<?php

namespace Utils\AIAssistant;

use Exception;
use Gemini;
use Orhanerday\OpenAi\OpenAi;
use Utils\Registry\AppConfig;

class AIClientFactory
{
    /**
     * @param $agent
     * @return AIClientInterface
     * @throws Exception
     */
    public static function create($agent): AIClientInterface
    {
        switch ($agent) {
            case "openai":
                return self::openAi();

            case "gemini":
                return self::gemini();
        }

        throw new Exception("Unsupported agent: " . $agent);
    }


    /**
     * @return GeminiClient
     * @throws Exception
     */
    private static function gemini(): GeminiClient
    {
        if(empty(AppConfig::$GEMINI_API_KEY)){
            throw new Exception('Gemini API key not set');
        }

        $timeOut = (AppConfig::$GEMINI_TIMEOUT) ?: 30;

        return new GeminiClient(Gemini::factory()
            ->withApiKey(AppConfig::$GEMINI_API_KEY)
            ->withHttpClient(new \GuzzleHttp\Client(['timeout' => $timeOut]))
            ->make());
    }

    /**
     * @return OpenAIClient
     * @throws Exception
     */
    private static function openAi(): OpenAIClient
    {
        if(empty(AppConfig::$OPENAI_API_KEY)){
            throw new Exception('OpenAI API key not set');
        }

        $timeOut = (AppConfig::$OPEN_AI_TIMEOUT) ?: 30;
        $openAi = new OpenAi(AppConfig::$OPENAI_API_KEY);
        $openAi->setTimeout($timeOut);

        return new OpenAIClient($openAi);
    }
}