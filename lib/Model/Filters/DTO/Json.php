<?php

namespace Model\Filters\DTO;

class Json implements IDto
{

    private bool $extract_arrays = false;
    private bool $escape_forward_slashes = false;
    /** @var list<string> */
    private array $translate_keys = [];
    /** @var list<string> */
    private array $do_not_translate_keys = [];
    /** @var list<string> */
    private array $context_keys = [];
    /** @var list<string> */
    private array $character_limit = [];

    public function setExtractArrays(bool $extract_arrays): void
    {
        $this->extract_arrays = $extract_arrays;
    }

    public function setEscapeForwardSlashes(bool $escape_forward_slashes): void
    {
        $this->escape_forward_slashes = $escape_forward_slashes;
    }

    /**
     * @param list<string> $translate_keys
     */
    public function setTranslateKeys(array $translate_keys): void
    {
        $this->translate_keys = $translate_keys;
    }

    /**
     * @param list<string> $do_not_translate_keys
     */
    public function setDoNotTranslateKeys(array $do_not_translate_keys): void
    {
        $this->do_not_translate_keys = $do_not_translate_keys;
    }

    /**
     * @param list<string> $context_keys
     */
    public function setContextKeys(array $context_keys): void
    {
        $this->context_keys = $context_keys;
    }

    /**
     * @param list<string> $character_limit
     */
    public function setCharacterLimit(array $character_limit): void
    {
        $this->character_limit = $character_limit;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): void
    {
        if (isset($data['extract_arrays'])) {
            $this->setExtractArrays($data['extract_arrays']);
        }

        if (isset($data['escape_forward_slashes'])) {
            $this->setEscapeForwardSlashes($data['escape_forward_slashes']);
        }

        if (isset($data['translate_keys'])) {
            $this->setTranslateKeys($data['translate_keys']);
        }

        if (isset($data['do_not_translate_keys'])) {
            $this->setDoNotTranslateKeys($data['do_not_translate_keys']);
        }

        if (isset($data['context_keys'])) {
            $this->setContextKeys($data['context_keys']);
        }

        if (isset($data['character_limit'])) {
            $this->setCharacterLimit($data['character_limit']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $format = [];

        $format['extract_arrays'] = $this->extract_arrays;
        $format['escape_forward_slashes'] = $this->escape_forward_slashes;
        $format['translate_keys'] = $this->translate_keys;

        if (!empty($this->do_not_translate_keys)) {
            $format['do_not_translate_keys'] = $this->do_not_translate_keys;
            unset($format['translate_keys']);
        }

        $format['context_keys'] = $this->context_keys;
        $format['character_limit'] = $this->character_limit;

        return $format;
    }

}
