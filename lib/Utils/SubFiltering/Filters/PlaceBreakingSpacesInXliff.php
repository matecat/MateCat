<?php

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;

class PlaceBreakingSpacesInXliff extends AbstractHandler {

    /**
     * @inheritDoc
     */
    public function transform( $segment ) {
        return str_replace(
                [ "\r\n", "\r", "\n", "\t", "" ],
                [
                        '&#13;&#10;',
                        '&#13;',
                        '&#10;',
                        '&#09;',
                        '&#157;'
                ], $segment );
    }
}
