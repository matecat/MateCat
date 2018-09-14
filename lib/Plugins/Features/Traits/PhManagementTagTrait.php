<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 11/09/18
 * Time: 19.27
 *
 */

namespace Features\Traits;


trait PhManagementTagTrait {

    /**
     * Decide whether remove initial tags or not
     *
     * @param $boolean
     * @param $segment
     *
     * @return bool
     */
    public function skipTagLessFeature( $boolean, $segment ) {
        /**
         * Ugly tag recognition, it's the easy way to decide whether a tag is a normal tag or a xml <ph> tag
         *
         * Filters do not use <ph> tags, so it comes directly from a not converted xliff.
         *
         */
        if( preg_match('/^<ph [^>]+>|<ph [^>]+>$/', $segment ) ){
            return true;
        }
        return false;
    }

}