<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/02/17
 * Time: 13.04
 *
 */

namespace Controller\API\App;


use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\API\Commons\Exceptions\ValidationError;
use Exception;
use Model\Teams\InvitedUser;
use Model\Teams\TeamDao;
use Model\Users\UserDao;
use TypeError;
use Utils\Url\CanonicalRoutes;

/**
 * Endpoint to get the call from emails link in the invitation emails
 *
 * Class TeamsInvitationsController
 * @package API\App
 */
class TeamsInvitationsController extends AbstractStatefulKleinController
{

    /**
     * @throws ValidationError
     * @throws Exception
     * @throws TypeError
     */
    public function collectBackInvitation(): void
    {
        $invite = new InvitedUser(
            $this->request->param('jwt'),
            $this->response,
            new TeamDao($this->getDatabase()),
            null,
            new UserDao($this->getDatabase())
        );
        $invite->prepareUserInvitedSignUpRedirect();
        $this->response->redirect(CanonicalRoutes::appRoot());
    }

}