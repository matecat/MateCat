<?php

namespace AIAssistant;

use Exception;
use INIT;
use Orhanerday\OpenAi\OpenAi;

class Client
{
    /**
     * @var OpenAi
     */
    private $openAi;

    /**
     * Client constructor.
     * @param OpenAi $openAi
     */
    public function __construct( OpenAi $openAi )
    {
        $this->openAi = $openAi;
    }

    /**
     * @param $word
     * @param $phrase
     * @param $target
     * @param callable $callback
     * @return mixed
     * @throws Exception
     */
    public function findContextForAWord($word, $phrase, $target, Callable $callback)
    {
        $phrase = strip_tags($phrase);
        $content = "Explain, in ".$target.", the meaning of '".$word."' when used in this context : '".$phrase."'";
        $model =  (INIT::$OPEN_AI_MODEL and INIT::$OPEN_AI_MODEL !== '') ? INIT::$OPEN_AI_MODEL : 'gpt-3.5-turbo';
        $maxTokens =  (INIT::$OPEN_AI_MAX_TOKENS and INIT::$OPEN_AI_MAX_TOKENS !== '') ? (int)INIT::$OPEN_AI_MAX_TOKENS : 500;
        $realMaxTokens = (4000 - (int)$maxTokens);

        $opts = [
            'model' => $model,
            'messages' => [
                [
                    "role" => "user",
                    "content" => $content
                ],
            ],
            'temperature' => 1.0,
            'max_tokens' => (int)$realMaxTokens,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            "stream" => true,
        ];

        $this->openAi->chat( $opts, $callback );
    }
}
