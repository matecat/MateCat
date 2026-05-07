<?php

namespace Model\Filters\DTO;

class Dita implements IDto
{

    /** @var list<string> */
    private array $do_not_translate_elements = [];

    /**
     * @param list<string> $do_not_translate_elements
     */
    public function setDoNotTranslateElements(array $do_not_translate_elements): void
    {
        $this->do_not_translate_elements = $do_not_translate_elements;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): void
    {
        if (isset($data['do_not_translate_elements'])) {
            $this->setDoNotTranslateElements($data['do_not_translate_elements']);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function jsonSerialize(): array
    {
        return [
            'do_not_translate_elements' => $this->do_not_translate_elements
        ];
    }

}
