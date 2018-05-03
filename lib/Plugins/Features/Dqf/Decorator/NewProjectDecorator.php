<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/07/2017
 * Time: 17:37
 */

namespace Features\Dqf\Decorator;

use AbstractDecorator;
use Features\Dqf\Utils\Functions;

class NewProjectDecorator extends AbstractDecorator {

    /**
     * @var \PHPTALWithAppend
     */
    protected $template ;

    public function decorate() {
        Functions::commonVarsForDecorator($this->template) ;
    }

}