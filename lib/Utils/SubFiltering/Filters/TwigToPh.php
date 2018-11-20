<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 17.34
 *
 */

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;

class TwigToPh extends AbstractHandler {

    /**
     * @param $segment
     *
     * @return string
     */
    public function transform( $segment ) {
        preg_match_all( '/{[{%#].*?[}%#]}/', $segment, $html, PREG_SET_ORDER );
        foreach ( $html as $pos => $twig_variable ) {
            //replace subsequent elements excluding already encoded
            $segment = preg_replace(
                    '/' . preg_quote( $twig_variable[0], '/' ) . '/',
                    '<ph id="__mtc_' . $this->getPipeline()->getNextId() . '" equiv-text="base64:' . base64_encode( $twig_variable[ 0 ] ) . '"/>',
                    $segment,
                    1
            );
        }

        return $segment;
    }

}