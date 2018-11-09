<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 17.20
 *
 */

namespace SubFiltering;


class CtrlCharsPlaceHoldToAscii extends AbstractChannelHandler {

    public function transform( $segment ) {

        //normal control characters should not be sent by the client
        $segment = str_replace(
                [
                        "\r", "\n", "\t",
                        "&#0A;", "&#0D;", "&#09;"
                ], "", $segment
        );

        //Replace br placeholders
        $segment = str_replace( Constants::crlfPlaceholder, "\r\n", $segment );
        $segment = str_replace( Constants::lfPlaceholder, "\n", $segment );
        $segment = str_replace( Constants::crPlaceholder, "\r", $segment );
        $segment = str_replace( Constants::tabPlaceholder, "\t", $segment );

        return $segment;

    }

}