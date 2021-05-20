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
use SubFiltering\Filters\Sprintf\SprintfLocker;

class SprintfToPH extends AbstractHandler {

    private $source;
    private $target;

    public function __construct( $source = null, $target = null) {
        parent::__construct();
        $this->source = $source;
        $this->target = $target;
    }

    /**
     * TestSet:
     * <code>
     * |%-4d|%-4d|
     * |%':4d|
     * |%-':4d|
     * |%-'04d|
     * %02.2f
     * %02d
     * %1$s!
     * %08b
     * 20%-os - ignored
     * 20%-dir - ignored
     * 20%-zar - ignored
     *</code>
     *
     * @param $segment
     * @return string
     */
    public function transform( $segment ) {

        $sprintfLocker = new SprintfLocker($this->source, $this->target);

        //placeholding
        $segment = $sprintfLocker->lock($segment);

        // Octal parsing is disabled due to Hungarian percentages 20%-os
        // preg_match_all( '/(?:\x25\x25)|(\x25(?:(?:[1-9]\d*)\$|\((?:[^\)]+)\))?(?:\+)?(?:0|\'[^$])?(?:-)?(?:\d+)?(?:\.(?:\d+))?(?:[b-fiosuxX]))/', $segment, $vars, PREG_SET_ORDER );
        preg_match_all( '/(?:\x25\x25)|(\x25(?:(?:[1-9]\d*)\$|\((?:[^\)]+)\))?(?:\+)?(?:-)?(?:0|\'[^$])?(?:\d+)?(?:\.(?:\d+))?(?:[b-fiosuxX]))/', $segment, $vars, PREG_SET_ORDER );
        foreach ( $vars as $pos => $variable ) {

            //replace subsequent elements excluding already encoded
            $segment = preg_replace(
                    '/' . preg_quote( $variable[ 0 ], '/' ) . '/',
                    '<ph id="__mtc_' . $this->getPipeline()->getNextId() . '" equiv-text="base64:' . base64_encode( $variable[ 0 ] ) . '"/>',
                    $segment,
                    1
            );
        }

        //revert placeholding
        $segment = $sprintfLocker->unlock($segment);

        return $segment;
    }

}