<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/05/25
 * Time: 15:11
 *
 */

namespace Views;

use AbstractControllers\BaseKleinViewController;
use AbstractControllers\IController;
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use Exception;
use Utils;

class ManageController extends BaseKleinViewController implements IController {

    protected string $_outsource_login_API = '//signin.translated.net/';

    /**
     * @throws Exception
     */
    public function renderView() {

        $this->checkLoginRequiredAndRedirect();

        $this->setView( "manage.html", [
                'outsource_service_login' => $this->_outsource_login_API,
                'split_enabled'           => true,
                'enable_outsource'        => true
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