<?php

namespace Utils\LQA\BxExG;

class Element {

    /**
     * @var ?string
     */
    public ?string $name = null;

    /**
     * @var array
     */
    public array $attributes = [];

    /**
     * @var Element[]
     */
    public array $children = [];

    /**
     * @param Element $anotherElement
     *
     * @return bool
     */
    public function correspondsTo( Element $anotherElement ): bool {
        return $this->name === $anotherElement->name and $this->attributes === $anotherElement->attributes;
    }

    /**
     * @return bool
     */
    public function isG(): bool {
        return $this->name === 'g';
    }

    /**
     * @return bool
     */
    public function isExOrBx(): bool {
        return $this->name === 'ex' or $this->name === 'bx';
    }

    /**
     * @return int
     */
    public function getTotalTagsCount(): int {
        $count = 1;
        $count += $this->incrementTagsCount( $this );

        return $count;
    }

    /**
     * @param Element $element
     *
     * @return int
     */
    private function incrementTagsCount( Element $element ): int {
        $c = 0;

        foreach ( $element->children as $child ) {
            $c++;
            $c += $this->incrementTagsCount( $child );
        }

        return $c;
    }

    /**
     * @return bool
     */
    public function hasNestedBxOrEx(): bool {
        return $this->searchForNestedBxOrEx( $this->children );
    }

    /**
     * @param $children
     *
     * @return bool
     */
    private function searchForNestedBxOrEx( $children ): bool {
        foreach ( $children as $child ) {
            if ( $child->isExOrBx() ) {
                return true;
            }

            return $this->searchForNestedBxOrEx( $child->children );
        }

        return false;
    }
}
