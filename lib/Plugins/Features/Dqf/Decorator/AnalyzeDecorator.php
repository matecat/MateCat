<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 29/08/2017
 * Time: 12:30
 */

namespace Features\Dqf\Decorator;


use AbstractDecorator;
use Features\Dqf\Utils\Functions;

class AnalyzeDecorator extends AbstractDecorator {
    /**
     * @var \PHPTALWithAppend
     */
    protected $template ;

    public function decorate() {
        Functions::commonVarsForDecorator( $this->template ) ;
    }
}