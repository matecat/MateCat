<?php

namespace AIAssistant;

use Orhanerday\OpenAi\OpenAi;

class Client
{
    private $openAi;

    public function __construct($apiKey)
    {
        $this->openAi = new OpenAi($apiKey);
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

        $chat = $this->openAi->chat([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    "role" => "user",
                    "content" => "Explain, in ".$target.", the meaning of '".$word."' when used in this context : '".$phrase."'"
                ],
            ],
            'temperature' => 1.0,
            'max_tokens' => 4000,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);

        $d = json_decode($chat);

        return $d->choices[0]->message->content;
    }
}
