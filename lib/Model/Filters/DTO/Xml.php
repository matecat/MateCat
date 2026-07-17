<?php

namespace Model\Filters\DTO;

class Xml implements IDto
{

    private bool $preserve_whitespace = false;
    /** @var list<string> */
    private array $translate_elements = [];
    /** @var list<string> */
    private array $do_not_translate_elements = [];
    /** @var list<string> */
    private array $translate_attributes = [];

    public function setPreserveWhitespace(bool $preserve_whitespace): void
    {
        $this->preserve_whitespace = $preserve_whitespace;
    }

    /**
     * @param list<string> $translate_elements
     */
    public function setTranslateElements(array $translate_elements): void
    {
        $this->translate_elements = $translate_elements;
    }

    /**
     * @param list<string> $do_not_translate_elements
     */
    public function setDoNotTranslateElements(array $do_not_translate_elements): void
    {
        $this->do_not_translate_elements = $do_not_translate_elements;
    }

    /**
     * @param list<string> $translate_attributes
     */
    public function setTranslateAttributes(array $translate_attributes): void
    {
        $this->translate_attributes = $translate_attributes;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): void
    {
        if (isset($data['preserve_whitespace'])) {
            $this->setPreserveWhitespace($data['preserve_whitespace']);
        }

        if (isset($data['translate_elements'])) {
            $this->setTranslateElements($data['translate_elements']);
        }

        if (isset($data['do_not_translate_elements'])) {
            $this->setDoNotTranslateElements($data['do_not_translate_elements']);
        }

        if (isset($data['translate_attributes'])) {
            $this->setTranslateAttributes($data['translate_attributes']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $format = [];

        $format['preserve_whitespace'] = $this->preserve_whitespace;
        $format['translate_elements'] = $this->translate_elements;

        if (!empty($this->do_not_translate_elements)) {
            $format['do_not_translate_elements'] = $this->do_not_translate_elements;
            unset($format['translate_elements']);
        }

        $format['translate_attributes'] = $this->translate_attributes;

        return $format;
    }

}
