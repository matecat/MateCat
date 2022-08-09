<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/09/17
 * Time: 18.04
 *
 */

namespace Features\Mmt\Controller;


use API\V2\Validators\LoginValidator;
use Engine;
use Engines_MMT;
use INIT;
use PHPTAL;
use Users\MetadataDao;

class RedirectMeController extends \BaseKleinViewController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function redirect() {
        $template_path = realpath( dirname( __FILE__ ) . '/../View/redirectMe.html' );
        $this->setView( $template_path );
        $this->view->setOutputMode( PHPTAL::HTML5 );

        $metadataStruct = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->get( $this->getUser()->uid, 'mmt' );

        $engineMMT = Engine::getInstance( $metadataStruct->value );

        /**
         * @var $engineMMT Engines_MMT
         */
        $this->view->basepath = INIT::$BASEURL;
        $this->view->license  = $engineMMT->extra_parameters[ 'MMT-License' ];
        $this->view->url      = "https://www.modernmt.com/license/me";

        $this->response->body( $this->view->execute() );
        $this->response->send();
    }

}