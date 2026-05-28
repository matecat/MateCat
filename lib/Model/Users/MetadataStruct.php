<?php

namespace Model\Users;

use JsonSerializable;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;

class MetadataStruct extends AbstractDaoObjectStruct implements IDaoStruct, JsonSerializable
{
    public string $id;
    public string $uid;
    public string $key;

    /** @var string|int|object|array */
    public string|int|object|array $value;

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'id'    => (int)$this->id,
            'uid'   => (int)$this->uid,
            'key'   => $this->key,
            'value' => $this->getValue(),
        ];
    }

    /**
     * @return int|float|object|string
     */
    public function getValue(): object|int|float|string
    {
        // in the case of numeric value, return int or float
        if (is_numeric($this->value)) {
            $float = (float)$this->value;

            return floor($float) == $float ? (int)$this->value : $float;
        }

        // in case of array, return an object
        if (is_array($this->value)) {
            return (object)$this->value;
        }

        // in case of serialized data, return an object
        if (is_string($this->value) && $this->looksSerialised($this->value)) {
            $unserialized = @unserialize($this->value, ['allowed_classes' => false]);
            if ($unserialized !== false) {
                return (object)$unserialized;
            }
        }

        // return a string
        return $this->value;
    }

    /**
     * Cheap structural pre-check so unserialize() is only
     * attempted on strings that look like PHP-serialised data.
     */
    private function looksSerialised(string $data): bool
    {
        $data = trim($data);

        if (strlen($data) < 2) {
            return false;
        }

        if ($data === 'N;') {
            return true;
        }

        return $data[1] === ':'
            && in_array($data[0], ['s', 'i', 'd', 'a', 'O', 'C', 'b'], true);
    }
}