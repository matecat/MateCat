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

class ManageController extends BaseKleinViewController implements IController
{

    protected string $_outsource_login_API = '//signin.translated.net/';

    protected function registerValidators(): void
    {
        $this->appendValidator(new ViewLoginRedirectValidator($this));
    }

    /**
     * @throws Exception
     */
    public function renderView(): never
    {
        $this->setView("manage.html", [
            'outsource_service_login' => $this->_outsource_login_API,
            // Named as the page reads them. The template used to rename these two on their way into
            // the script block; now that the whole set is serialised under its own keys, the name the
            // controller chooses is the name the page sees.
            'splitFeatureAvailable' => new PHPTalBoolean(true),
            'enable_outsource' => new PHPTalBoolean(true),
            // Preserves what this page has always sent. Nothing assigned this variable here, so the
            // template's `|string:false` fallback supplied it on every render — see the todo doc, the
            // missing assignment looks like an oversight rather than an intent.
            'not_empty_default_tm_key' => new PHPTalBoolean(false),
        ]);

        $activity = new ActivityLogStruct();
        $activity->action = ActivityLogStruct::ACCESS_MANAGE_PAGE;
        $activity->ip = Utils::getRealIpAddr();
        $activity->uid = $this->user->uid;
        $activity->event_date = date('Y-m-d H:i:s');
        Activity::save($activity);

        $this->render();
    }

}