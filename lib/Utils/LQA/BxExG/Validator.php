<?php

namespace LQA\BxExG;

use LQA\QA;

class Validator {

    /**
     * To get array map of QA
     *
     * @var QA
     */
    private $qa;

    /**
     * Validator constructor.
     *
     * @param QA $qa
     */
    public function __construct( QA $qa ) {
        $this->qa = $qa;
    }

    /**
     * Returns three type of errors (an empty array is returned if no errors were found):
     *
     * - ERR_EX_BX_NESTED_IN_G:     [ERROR]    when there is nested <bx> or <ex> inside a <g> in target and NOT in source
     * - ERR_EX_BX_COUNT_MISMATCH:  [ERROR]    when there is a tag count mismatch between source and target
     * - ERR_EX_BX_WRONG_POSITION:  [WARNING]  when there is nested <bx> or <ex> inside a <g> in source and NOT in target or any other mismatch error
     *
     * @return array
     */
    public function validate() {

        $qa = $this->qa;

        // compare source and target tags maps
        $sourceMap = Mapper::extract( $qa->getSourceSeg() );
        $targetMap = Mapper::extract( $qa->getTargetSeg() );

        // if no <bx> or <ex> are found in source and target no errors are returned
        if(!$this->doesAMapHaveOneBxOrEx($sourceMap) and !$this->doesAMapHaveOneBxOrEx($targetMap)){
            return [];
        }

        // if maps are equal return empty errors array
        if ( $sourceMap == $targetMap ) {
            return [];
        }

        $errors = [];

        $sourceTagCount = 0;
        $targetTagCount = 0;

        /** @var $element Element */
        foreach ( $sourceMap as $i => $element ) {

            // for later (check for ERR_EX_BX_COUNT_MISMATCH)
            $sourceTagCount += $element->getTotalTagsCount();

            // loop target map to find corresponding <g> element
            /** @var $targetElement Element */
            foreach ( $targetMap as $index => $targetElement ) {
                if ( $element->correspondsTo( $targetElement ) and $element->isG() ) {
                    // check for ERR_EX_BX_NESTED_IN_G
                    if ( !$element->hasNestedBxOrEx() and $targetElement->hasNestedBxOrEx() ) {
                        $errors[] = $qa::ERR_EX_BX_NESTED_IN_G;
                    }
                }
            }
        }

        /** @var $element Element */
        foreach ( $targetMap as $index => $element ) {
            $targetTagCount += $element->getTotalTagsCount();
        }

        // check for ERR_EX_BX_COUNT_MISMATCH
        if ( $sourceTagCount !== $targetTagCount ) {
            $errors[] = $qa::ERR_EX_BX_COUNT_MISMATCH;
        }

        // return a generic ERR_EX_BX_WRONG_POSITION if there is no ERR_EX_BX_NESTED_IN_G or ERR_EX_BX_COUNT_MISMATCH errors
        if ( !in_array( $qa::ERR_EX_BX_COUNT_MISMATCH, $errors ) and !in_array( $qa::ERR_EX_BX_NESTED_IN_G, $errors ) ) {
            $errors[] = $qa::ERR_EX_BX_WRONG_POSITION;
        }

        return $errors;
    }

    /**
     * @param array $map
     *
     * @return bool
     */
    private function doesAMapHaveOneBxOrEx(array $map = [])
    {
        if (empty($map)){
            return false;
        }

        /** @var Element $element */
        foreach ($map as $element){
            if($element->isExOrBx() or $element->hasNestedBxOrEx()){
                return true;
            }
        }

        return false;
    }
}