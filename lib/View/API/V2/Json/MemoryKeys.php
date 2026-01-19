<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 12:56
 */

namespace View\API\V2\Json;


use Model\TmKeyManagement\MemoryKeyStruct;

class MemoryKeys
{

    /**
     * @var MemoryKeyStruct[]
     */
    protected array $data = [];

    /**
     * Project constructor.
     *
     * @param MemoryKeyStruct[] $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public static function renderItem(MemoryKeyStruct $keyStruct): array
    {
        return [
            'key' => $keyStruct->tm_key->key,
            'name' => $keyStruct->tm_key->name
        ];
    }

    public function render(): array
    {
        $out = [];
        foreach ($this->data as $keyStruct) {
            $keyType = 'private_keys';
            if ($keyStruct->tm_key->isShared()) {
                $keyType = 'shared_keys';
            }

            $out[$keyType][] = $this->renderItem($keyStruct);
        }

        return $out;
    }

}