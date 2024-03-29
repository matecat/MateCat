<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/09/17
 * Time: 16.25
 *
 */

namespace Features\Mmt\Decorator;

use AbstractDecorator;
use PHPTALWithAppend;

class NewProjectDecorator extends AbstractDecorator {

    /**
     * @var PHPTALWithAppend
     */
    protected $template ;

    public function decorate(){
//        $this->template->append( 'footer_js', Routes::staticSrc( 'mmt_extensions.js' ) );
    }

}