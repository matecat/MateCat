<?php

namespace FiltersXliffConfig\Xliff;

use JsonSerializable;

class XliffConfigModel implements JsonSerializable
{
    private $xliff12;
    private $xliff20;

    /**
     * XliffConfigModel constructor.
     * @param $xliff12
     * @param $xliff20
     */
    public function __construct($xliff12, $xliff20)
    {
        $this->xliff12 = $xliff12;
        $this->xliff20 = $xliff20;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'xliff12' => $this->xliff12,
            'xliff20' => $this->xliff20,
        ];
    }
}
