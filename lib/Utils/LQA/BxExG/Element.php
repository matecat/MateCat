<?php

namespace LQA\BxExG;

class Element {

    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $attributes = [];

    /**
     * @var Element[]
     */
    public $children = [];

    /**
     * @param Element $anotherElement
     *
     * @return bool
     */
    public function correspondsTo( Element $anotherElement ) {
        return $this->name === $anotherElement->name and $this->attributes === $anotherElement->attributes;
    }

    /**
     * @return bool
     */
    public function isG() {
        return $this->name === 'g';
    }

    /**
     * @return bool
     */
    public function isExOrBx() {
        return $this->name === 'ex' or $this->name === 'bx';
    }

    /**
     * @return int
     */
    public function getTotalTagsCount() {
        $count = 1;
        $count += $this->incrementTagsCount( $this );

        return $count;
    }

    /**
     * @param Element $element
     *
     * @return int
     */
    private function incrementTagsCount( Element $element ) {
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
    public function hasNestedBxOrEx() {
        return $this->searchForNestedBxOrEx( $this->children );
    }

    /**
     * @param $children
     *
     * @return bool
     */
    private function searchForNestedBxOrEx( $children ) {
        foreach ( $children as $child ) {
            if ( $child->isExOrBx() ) {
                return true;
            }

            return $this->searchForNestedBxOrEx( $child->children );
        }

        return false;
    }
}
