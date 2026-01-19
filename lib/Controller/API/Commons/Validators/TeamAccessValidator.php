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
        $id_team = $this->request->param('id_team');
        $name = (!empty($this->request->param('team_name'))) ? base64_decode($this->request->param('team_name')) : null;

        if ($name !== null and strtolower($name) !== Teams::PERSONAL) {
            $this->team = (new MembershipDao())->setCacheTTL(60 * 10)->findTeamByIdAndName(
                $id_team,
                $name
            );
        } else {
            $this->team = (new MembershipDao())->setCacheTTL(60 * 10)->findTeamByIdAndUser(
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