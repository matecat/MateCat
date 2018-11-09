<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 17.09
 *
 */

namespace SubFiltering;


class LtGtEncode extends AbstractChannelHandler {

    public function transform( $segment ) {
        // restore < e >
        $segment = str_replace("<", "&lt;", $segment);
        $segment = str_replace(">", "&gt;", $segment);
        return $segment;
    }

}