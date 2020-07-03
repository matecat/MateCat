<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 17.20
 *
 */

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;
use SubFiltering\Commons\Constants;

class RestoreTabsPlaceholders extends AbstractHandler {

    public function transform( $segment ) {

        // Restore tabs placeholders from persistency layer (layer 0):
        //
        // +-------------------+--------------------+
        // | SOURCE            | TARGET             |
        // +-------------------+--------------------+
        // | Esempio &#09;test | Test	example     |
        // +-------------------+--------------------+
        //
        return str_replace( ["&#09;", "	"], Constants::tabPlaceholder, $segment );
    }

}