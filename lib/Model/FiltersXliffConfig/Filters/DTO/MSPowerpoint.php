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
     * @param bool|null $extract_doc_properties
     */
    public function setExtractDocProperties(?bool $extract_doc_properties): void
    {
        $this->extract_doc_properties = $extract_doc_properties;
    }

    /**
     * @param bool|null $extract_comments
     */
    public function setExtractComments(?bool $extract_comments): void
    {
        $this->extract_comments = $extract_comments;
    }

    /**
     * @param bool|null $extract_hidden_slides
     */
    public function setExtractHiddenSlides(?bool $extract_hidden_slides): void
    {
        $this->extract_hidden_slides = $extract_hidden_slides;
    }

    /**
     * @param bool|null $extract_notes
     */
    public function setExtractNotes(?bool $extract_notes): void
    {
        $this->extract_notes = $extract_notes;
    }

    /**
     * @param array $translate_slides
     */
    public function setTranslateSlides(array $translate_slides): void
    {
        $this->translate_slides = $translate_slides;
    }

    /**
     * @param $data
     */
    public function fromArray($data)
    {
        if(isset($data['extract_doc_properties'])){
            $this->setExtractDocProperties($data['extract_doc_properties']);
        }

        if(isset($data['extract_comments'])){
            $this->setExtractComments($data['extract_comments']);
        }

        if(isset($data['extract_hidden_slides'])){
            $this->setExtractHiddenSlides($data['extract_hidden_slides']);
        }

        if(isset($data['translate_slides'])){
            $this->setTranslateSlides($data['translate_slides']);
        }

        if(isset($data['extract_notes'])){
            $this->setExtractNotes($data['extract_notes']);
        }
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