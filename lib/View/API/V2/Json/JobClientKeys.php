<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 12:56
 */

namespace View\API\V2\Json;


use Utils\TmKeyManagement\ClientTmKeyStruct;

class JobClientKeys
{

    /**
     * @var ClientTmKeyStruct[]
     */
    protected array $data = [];

    /**
     * Project constructor.
     *
     * @param ClientTmKeyStruct[] $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public static function renderItem(ClientTmKeyStruct $keyStruct): array
    {
        return [
                "key"  => $keyStruct->key,
                "r"    => ($keyStruct->r),
                "w"    => ($keyStruct->w),
                "name" => $keyStruct->name
        ];
    }

    /**
     * @return array
     */
    public function render(): array
    {
        $out = [];
        foreach ($this->data as $keyStruct) {
            $out[] = $this->renderItem($keyStruct);
        }

        return $out;
    }

}