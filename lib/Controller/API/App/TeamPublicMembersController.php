<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Exception;
use Model\Teams\MembershipDao;
use ReflectionException;
use RuntimeException;
use TypeError;
use View\API\V2\Json\Membership;

class TeamPublicMembersController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $this->appendValidator(new TeamAccessValidator($this));
    }

    /**
     * Get a team members list
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws Exception
     * @throws TypeError
     */
    public function publicList(): void
    {
        $memberships = (new MembershipDao($this->db()))->setCacheTTL(60 * 60 * 24)->getMemberListByTeamId($this->request->param('id_team'));
        $formatter = new Membership($memberships);
        $this->response->json($formatter->renderPublic());
    }

}