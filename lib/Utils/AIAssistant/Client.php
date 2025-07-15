<?php

namespace Utils\AIAssistant;

use Exception;
use Orhanerday\OpenAi\OpenAi;
use Utils\Registry\AppConfig;

class Client {
    /**
     * @var OpenAi
     */
    private $openAi;

    /**
     * Client constructor.
     *
     * @param OpenAi $openAi
     */
    public function __construct( OpenAi $openAi ) {
        $this->openAi = $openAi;
    }

    /**
     * @param          $word
     * @param          $phrase
     * @param          $target
     * @param callable $callback
     *
     * @return mixed
     * @throws Exception
     */
    public function findContextForAWord( $word, $phrase, $target, callable $callback ) {
        $phrase        = strip_tags( $phrase );
        $content       = "Explain, in " . $target . ", the meaning of '" . $word . "' when used in this context : '" . $phrase . "'";
        $model         = ( AppConfig::$OPEN_AI_MODEL and AppConfig::$OPEN_AI_MODEL !== '' ) ? AppConfig::$OPEN_AI_MODEL : 'gpt-3.5-turbo';
        $maxTokens     = ( AppConfig::$OPEN_AI_MAX_TOKENS and AppConfig::$OPEN_AI_MAX_TOKENS !== '' ) ? (int)AppConfig::$OPEN_AI_MAX_TOKENS : 500;
        $realMaxTokens = ( 4000 - (int)$maxTokens );

        $opts = [
                'model'             => $model,
                'messages'          => [
                        [
                                "role"    => "user",
                                "content" => $content
                        ],
                ],
                'temperature'       => 1.0,
                'max_tokens'        => (int)$realMaxTokens,
                'frequency_penalty' => 0,
                'presence_penalty'  => 0,
                "stream"            => true,
        ];

        $this->openAi->chat( $opts, $callback );
    }
}
