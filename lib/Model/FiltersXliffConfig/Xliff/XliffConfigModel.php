<?php

namespace FiltersXliffConfig\Xliff;

use DomainException;
use FiltersXliffConfig\Xliff\DTO\Xliff12Rule;
use FiltersXliffConfig\Xliff\DTO\Xliff20Rule;
use JsonSerializable;

class XliffConfigModel implements JsonSerializable
{
    private $xliff12 = [];
    private $xliff20 = [];

    /**
     * XliffConfigModel constructor.
     *
     * @param Xliff12Rule[] $xliff12
     * @param Xliff20Rule[] $xliff20
     */
    public function __construct(array $xliff12, array $xliff20)
    {
        $this->checkRules($xliff12);
        $this->checkRules($xliff20);

        $this->xliff12 = $xliff12;
        $this->xliff20 = $xliff20;
    }

    /**
     * @param $rules
     */
    private function checkRules($rules)
    {
        foreach ($rules as $rule){
            if(!$rule instanceof Xliff12Rule){
                throw new DomainException("Not valid rules");
            }
        }
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
