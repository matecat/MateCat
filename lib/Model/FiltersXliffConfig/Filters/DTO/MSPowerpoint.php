<?php

namespace FiltersXliffConfig\Filters\DTO;

use JsonSerializable;

class MSPowerpoint implements JsonSerializable
{
    private $extract_doc_properties;
    private $extract_comments;
    private $extract_hidden_slides;
    private $extract_notes;
    private $translate_slides;

    /**
     * MSPowerpoint constructor.
     * @param bool|null $extract_doc_properties
     * @param bool|null $extract_comments
     * @param bool|null $extract_hidden_slides
     * @param bool|null $extract_notes
     * @param array $translate_slides
     */
    public function __construct(
        ?bool $extract_doc_properties = null,
        ?bool $extract_comments = null,
        ?bool $extract_hidden_slides = null,
        ?bool $extract_notes = null,
        array $translate_slides = []
    )
    {
        $this->extract_doc_properties = $extract_doc_properties;
        $this->extract_comments = $extract_comments;
        $this->extract_hidden_slides = $extract_hidden_slides;
        $this->extract_notes = $extract_notes;
        $this->translate_slides = $translate_slides;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'extract_doc_properties' => $this->extract_doc_properties,
            'extract_comments' => $this->extract_comments,
            'extract_hidden_slides' => $this->extract_hidden_slides,
            'extract_notes' => $this->extract_notes,
            'translate_slides' => $this->translate_slides,
        ];
    }
}