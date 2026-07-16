<?php

namespace Model\Filters\DTO;

use DomainException;

class Yaml implements IDto
{

    /** @var list<string> */
    private array $translate_keys = [];
    /** @var list<string> */
    private array $do_not_translate_keys = [];
    /** @var list<string> */
    private array $context_keys = [];
    /** @var list<string> */
    private array $character_limit = [];
    private ?string $inner_content_type = null;

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
     * @throws DomainException
     */
    public function setInnerContentType(?string $inner_content_type): void
    {
        $mimeTypes = [
            'text/html',
            'text/xml',
            'application/xml',
            'text/csv',
            'application/json',
            'text/markdown',
            'text/x-markdown',
        ];

        if (!in_array($inner_content_type, $mimeTypes)) {
            throw new DomainException(
                "YAML Inner content type not valid. Allowed values: ['text/html', 'text/xml', 'application/xml', 'text/csv', 'application/json', 'text/markdown', 'text/x-markdown']"
            );
        }

        $this->inner_content_type = $inner_content_type;
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
     *
     * @throws DomainException
     */
    public function fromArray(array $data): void
    {
        if (isset($data['translate_keys'])) {
            $this->setTranslateKeys($data['translate_keys']);
        }

        if (isset($data['do_not_translate_keys'])) {
            $this->setDoNotTranslateKeys($data['do_not_translate_keys']);
        }

        if (isset($data['inner_content_type'])) {
            $this->setInnerContentType($data['inner_content_type']);
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

        $format['translate_keys'] = $this->translate_keys;

        if (!empty($this->do_not_translate_keys)) {
            $format['do_not_translate_keys'] = $this->do_not_translate_keys;
            unset($format['translate_keys']);
        }

        $format['inner_content_type'] = $this->inner_content_type;
        $format['context_keys'] = $this->context_keys;
        $format['character_limit'] = $this->character_limit;

        return $format;
    }

}
