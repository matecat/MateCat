<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 21/09/17
 * Time: 15.38
 *
 */

namespace Controller\API\Commons\Validators;


use DomainException;
use Utils\Tools\Utils;

class WhitelistAccessValidator extends Base {

    public function _validate(): void {

        #Block all not whitelisted IPs
        $ipWhiteList = [
                "/^10\.30\.1\..*/",
                "/^10\.3\.14\..*/",
                "/^10\.3\.15\..*/",
                "/^10\.6\..*/",
                "/^10\.128\..*/",
                "/^10\.144\..*/",
                "/^172\.(?:1[6-9]|2[0-9]|3[0-1])\..*/",
                "/^149\.7\.212\..*/",
                "/^127\.0\.0\..*/",
                "/^93\.43\.95\.1(?:29|3[0-4])/",
        ];

        if ( preg_replace( $ipWhiteList, 'ALLOW', Utils::getRealIpAddr() ) !== 'ALLOW' ) {
            throw new DomainException( "Invalid Get: not authorized domain: " . Utils::getRealIpAddr(), 403 );
        }

    }

}