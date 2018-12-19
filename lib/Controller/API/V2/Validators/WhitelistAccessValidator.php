<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 21/09/17
 * Time: 15.38
 *
 */

namespace API\V2\Validators;


use AbstractControllers\IController;
use API\V2\KleinController;
use Exceptions\NotFoundException;
use Utils;

class WhitelistAccessValidator extends Base {

    /**
     * @var KleinController
     */
    protected $controller;

    public function __construct( IController $controller ) {

        if( method_exists( $controller, 'getRequest' ) ){
            /**
             * @var $controller KleinController
             */
            parent::__construct( $controller->getRequest() );
        }

        $this->controller = $controller;

    }

    public function _validate() {

        #Block all not whitelisted IPs
        $ipWhiteList = [
                "/^10\.30\.1\..*/",
                "/^10\.3\.14\..*/",
                "/^10\.3\.15\..*/",
                "/^10\.6\..*/",
                "/^172\.18\..*/",
                "/^149\.7\.212\..*/",
                "/^2\.229\.60\.78/",
                "/^127\.0\.0\..*/",
                "/^93\.43\.95\.132/",

        ];

        if( preg_replace( $ipWhiteList, 'ALLOW', Utils::getRealIpAddr() ) !== 'ALLOW' ){
            throw new NotFoundException( "Not Found.", 404 );
        }

    }

}