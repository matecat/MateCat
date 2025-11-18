<?php


namespace Utils\Engines\Results;

class MTResponse
{

    public string $translatedText = "";
    public string $sentence_confidence;
    public mixed $error = "";

    public function __construct(array $result)
    {
        $this->error = new ErrorResponse();
        if (array_key_exists("data", $result)) {
            $this->translatedText = $result['data']['translations'][0]['translatedText'];
            if (isset($result['data']['translations'][0]['sentence_confidence'])) {
                $this->sentence_confidence = $result['data']['translations'][0]['sentence_confidence'];
            }
        }

        if (array_key_exists("error", $result)) {
            $this->error = new ErrorResponse($result['error']);
        }
    }

    public function get_as_array(): array
    {
        if ($this->error != "") {
            $this->error = $this->error->get_as_array();
        }

        return (array)$this;
    }

}