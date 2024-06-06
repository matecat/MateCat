<?php

namespace FiltersXliffConfig\Xliff\DTO;

use DomainException;
use JsonSerializable;

class XliffConfigModel implements JsonSerializable {

    const XLIFF_12 = 'xliff12';
    const XLIFF_20 = 'xliff20';

    /**
     * @var XliffRuleInterface[]
     */
    private $ruleSets = [
            self::XLIFF_12 => [],
            self::XLIFF_20 => [],
    ];

    /**
     * @param XliffRuleInterface $rule
     */
    public function addRule( XliffRuleInterface $rule ) {
        if ( $rule instanceof Xliff20Rule ) {
            $this->validateDuplicatedStates( $rule, self::XLIFF_20 );
            $this->ruleSets[ self::XLIFF_20 ][] = $rule;
        } elseif ( $rule instanceof Xliff12Rule ) {
            $this->validateDuplicatedStates( $rule, self::XLIFF_12 );
            $this->ruleSets[ self::XLIFF_12 ][] = $rule;
        }
    }

    /**
     * @param XliffRuleInterface $rule
     * @param string             $type
     *
     * @return void
     */
    public function validateDuplicatedStates( XliffRuleInterface $rule, $type ) {
        foreach ( $this->ruleSets[ $type ] as $existentRule ) {
            $stateIntersect = array_intersect( $existentRule->getStates(), $rule->getStates() );
            if ( !empty( $stateIntersect ) ) {
                throw new DomainException( "Duplicated states: " . implode( ", ", $stateIntersect ), 400 );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public
    function jsonSerialize() {
        return $this->ruleSets;
    }

    /**
     * @inheritDoc
     */
    public
    function __toString() {
        return json_encode( $this->ruleSets );
    }
}
