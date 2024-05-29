<?php

namespace FiltersXliffConfig\Xliff;

use DomainException;
use FiltersXliffConfig\Xliff\DTO\Xliff12Rule;
use FiltersXliffConfig\Xliff\DTO\Xliff20Rule;
use JsonSerializable;
use Serializable;

class XliffConfigModel implements JsonSerializable, Serializable
{
    private $xliff12 = [];
    private $xliff20 = [];

    /**
     * @param Xliff12Rule $rule
     */
    public function addRule(Xliff12Rule $rule)
    {
        if($rule instanceof Xliff20Rule){
            $this->xliff20[] = $rule;
        } else {
            $this->xliff12[] = $rule;
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

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return json_encode([
            'xliff12' => $this->xliff12,
            'xliff20' => $this->xliff20,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        return json_decode($serialized);
    }
}
