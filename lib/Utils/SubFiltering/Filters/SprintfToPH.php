<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 17.09
 *
 */

namespace SubFiltering\Filters;


use SubFiltering\Commons\AbstractHandler;

class SprintfToPH extends AbstractHandler {

    public function transform( $segment ) {
        //
        preg_match_all( '/(?:\x25\x25)|(\x25(?:(?:[1-9]\d*)\$|\((?:[^\)]+)\))?(?:\+)?(?:0|\'[^$])?(?:-)?(?:\d+)?(?:\.(?:\d+))?(?:[b-fiosuxX]))/', $segment, $vars, PREG_SET_ORDER );
        foreach ( $vars as $pos => $variable ) {
            //replace subsequent elements excluding already encoded
            $segment = preg_replace(
                    '/' . preg_quote( $variable[0], '/' ) . '/',
                    '<ph id="__mtc_' . $this->getPipeline()->getNextId() . '" equiv-text="base64:' . base64_encode( $variable[ 0 ] ) . '"/>',
                    $segment,
                    1
            );
        }
        return $segment;
    }

}