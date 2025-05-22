<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 15/05/25
 * Time: 11:14
 *
 */

namespace API\Commons\ViewValidators;

use AbstractControllers\BaseKleinViewController;
use API\Commons\Validators\Base;
use Bootstrap;

class MandatoryKeysValidator extends Base {

    /**
     * @param BaseKleinViewController $controller
     */
    public function __construct( BaseKleinViewController $controller ) {
        parent::__construct( $controller );
    }

    /**
     * @inheritDoc
     */
    protected function _validate(): void {
        if ( !Bootstrap::areMandatoryKeysPresent() ) {
            /** @var BaseKleinViewController $controller */
            $controller = $this->controller;
            $controller->setView( 'badConfiguration.html', [], 503 );
            $controller->render();
        }
    }
}