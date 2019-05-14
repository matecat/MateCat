<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/05/19
 * Time: 19.37
 *
 */

namespace SubFiltering\Filters;


use SubFiltering\Commons\AbstractHandler;

class RemoveDangerousChars extends AbstractHandler {

    public function transform( $segment ) {

        //clean invalid xml entities ( characters with ascii < 32 and different from 0A, 0D and 09
        $regexpEntity = '/&#x(0[0-8BCEF]|1[0-9A-F]|7F);/u';

        //remove binary chars in some xliff files
        $regexpAscii  = '/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}]/u';

        $segment = preg_replace( $regexpAscii, '', $segment );
        $segment = preg_replace( $regexpEntity, '', $segment );

        return $segment;

    }

}