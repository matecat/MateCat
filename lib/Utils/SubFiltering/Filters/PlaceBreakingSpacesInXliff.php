<?php

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;

class PlaceBreakingSpacesInXliff extends AbstractHandler {

    /**
     * @inheritDoc
     */
    public function transform( $segment ) {
        $segment = str_replace(
                [ "\r\n", "\r", "\n", "\t" ],
                [
                        '&#13;&#10;',
                        '&#13;',
                        '&#10;',
                        '&#09;',
                ], $segment );

        // handle 9D character (TAB)
        return preg_replace('/\x9d/', '&#09;', $segment);
    }
}