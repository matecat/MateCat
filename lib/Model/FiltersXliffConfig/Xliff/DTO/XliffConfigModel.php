<?php

namespace FiltersXliffConfig\Xliff\DTO;

use JsonSerializable;

class XliffConfigModel implements JsonSerializable {
    private $xliff12 = [];
    private $xliff20 = [];

    /**
     * @param XliffRuleInterface $rule
     */
    public function addRule( XliffRuleInterface $rule ) {
        if ( $rule instanceof Xliff20Rule ) {
            $rule->validateDuplicatedStates( $this->xliff20 );
            $this->xliff20[] = $rule;

        } elseif ( $rule instanceof Xliff12Rule ) {
            $rule->validateDuplicatedStates( $this->xliff12 );
            $this->xliff12[] = $rule;
        }
    }

    /**
     * @inheritDoc
     */
    public
    function jsonSerialize() {
        return [
                'xliff12' => $this->xliff12,
                'xliff20' => $this->xliff20,
        ];
    }

    /**
     * @inheritDoc
     */
    public
    function __toString() {
        return json_encode( [
                'xliff12' => $this->xliff12,
                'xliff20' => $this->xliff20,
        ] );
    }
}
