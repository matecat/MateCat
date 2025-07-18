<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/05/25
 * Time: 15:11
 *
 */

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Controller\Abstracts\IController;
use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Exception;
use Model\ActivityLog\Activity;
use Model\ActivityLog\ActivityLogStruct;
use Utils\Templating\PHPTalBoolean;
use Utils\Tools\Utils;

class ManageController extends BaseKleinViewController implements IController {

    protected string $_outsource_login_API = '//signin.translated.net/';

    protected function afterConstruct() {
        $this->appendValidator( new ViewLoginRedirectValidator( $this ) );
    }

    /**
     * @throws Exception
     */
    public function renderView() {

        $this->setView( "manage.html", [
                'outsource_service_login' => $this->_outsource_login_API,
                'split_enabled'           => new PHPTalBoolean( true ),
                'enable_outsource'        => new PHPTalBoolean( true )
        ] );

        $activity             = new ActivityLogStruct();
        $activity->action     = ActivityLogStruct::ACCESS_MANAGE_PAGE;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

        $this->render();

    }

}