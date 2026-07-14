<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/02/17
 * Time: 17.01
 *
 */

namespace Controller\API\Commons\Validators;


use Controller\API\Commons\Exceptions\AuthorizationError;
use Model\Teams\MembershipDao;
use Model\Teams\TeamStruct;
use Utils\Constants\Teams;

class TeamAccessValidator extends Base
{

    /**
     * @var TeamStruct|null
     */
    public ?TeamStruct $team = null;


    public function _validate(): void
    {
        // First constructor arg: when true, a matching (id_team, team_name) pair grants access
        // WITHOUT verifying team membership. Enabled only by the public members endpoint. When
        // absent/false every consumer is membership-gated, so the team_name request parameter
        // cannot be used to bypass authorization (CWE-639 IDOR).
        $allowPublicNameLookup = !empty($this->args[0]);

        $id_team = $this->request->param('id_team');
        $name = (!empty($this->request->param('team_name'))) ? base64_decode($this->request->param('team_name')) : null;

        if ($allowPublicNameLookup and $name !== null and strtolower($name) !== Teams::PERSONAL) {
            $this->team = (new MembershipDao($this->controller->getDatabase()))->setCacheTTL(60 * 10)->findTeamByIdAndName(
                $id_team,
                $name
            );
        } else {
            $this->team = (new MembershipDao($this->controller->getDatabase()))->setCacheTTL(60 * 10)->findTeamByIdAndUser(
                $id_team,
                $this->controller->getUser()
            );
        }

        if (empty($this->team)) {
            throw new AuthorizationError("Not Authorized", 401);
        }

        if (method_exists($this->controller, 'setTeam')) {
            $this->controller->setTeam($this->team);
        }
    }

}