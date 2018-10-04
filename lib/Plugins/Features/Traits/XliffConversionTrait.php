<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 11/09/18
 * Time: 19.08
 *
 */

namespace Features\Traits;


trait XliffConversionTrait {

    /**
     * Override the instance decision to convert or not the normal xlf/xliff files
     *
     * @param      $forceXliff
     *
     * @param      $_userIsLogged
     *
     * @param null $filePath
     *
     * @return bool
     */
    public function forceXLIFFConversion( $forceXliff, $_userIsLogged, $filePath =  null ) {
        if( !$_userIsLogged ) {
            return $forceXliff;
        }
        return false;
    }

}