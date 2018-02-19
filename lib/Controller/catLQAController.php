<?php
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use Exceptions\NotFoundError;


/**
 * Description of catController
 *
 * @property CatDecorator decorator
 *
 * @deprecated
 *
 */
class catLQAController extends catController {

    public function __construct() {

        parent::__construct();
        parent::makeTemplate( 'lqa.html' );

    }

    public static function isRevision(){
        //TODO: IMPROVE
        $_from_url   = parse_url( $_SERVER[ 'REQUEST_URI' ] );
        $is_revision_url = strpos( $_from_url[ 'path' ], "/revise" ) === 0;
        $is_lqa_url = strpos( $_from_url[ 'path' ], "/lqa" ) === 0;
        return $is_revision_url || $is_lqa_url;
    }

    public function setTemplateVars() {
        parent::setTemplateVars();
        $this->template->review_type = "extended-lqa";

//        $this->template->append('footer_js', \Features\Paypal\Utils\Routes::staticSrc('build/paypal-lqa-build.js') );
//        $this->template->append('css_resources', \Features\Paypal\Utils\Routes::staticSrc('build/paypal-build.css') );

    }

}
