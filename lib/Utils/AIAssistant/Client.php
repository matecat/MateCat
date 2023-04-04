<?php

namespace AIAssistant;

use Orhanerday\OpenAi\OpenAi;

class Client
{
    /**
     * @var OpenAi
     */
    private $openAi;

    /**
     * Client constructor.
     * @param $apiKey
     */
    public function __construct($apiKey)
    {
        $timeOut = (\INIT::$OPEN_AI_TIMEOUT) ? \INIT::$OPEN_AI_TIMEOUT : 30;

        $this->openAi = new OpenAi($apiKey);
        $this->openAi->setTimeout($timeOut);
    }

    /**
     * @param $word
     * @param $phrase
     * @param $target
     * @return mixed
     * @throws \Exception
     */
    public function findContextForAWord($word, $phrase, $target)
    {
        $phrase = strip_tags($phrase);
        $content = "Explain, in ".$target.", the meaning of '".$word."' when used in this context : '".$phrase."'";
        $model =  (\INIT::$OPEN_AI_MODEL and \INIT::$OPEN_AI_MODEL !== '') ? \INIT::$OPEN_AI_MODEL : 'gpt-3.5-turbo';

        $chat = $this->openAi->chat([
            'model' => $model,
            'messages' => [
                [
                    "role" => "user",
                    "content" => $content
                ],
            ],
            'temperature' => 1.0,
            'max_tokens' => 4000,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);

        $curlInfo = $this->openAi->getCURLInfo();

        if($curlInfo['http_code'] !== 200){
            throw new \Exception('Open AI API unexpected error');
        }

        $d = json_decode($chat);

        \Log::doJsonLog('Response to `'.$content.'` from Open AI API: ' . $chat);

        return $d->choices[0]->message->content;
    }
}
