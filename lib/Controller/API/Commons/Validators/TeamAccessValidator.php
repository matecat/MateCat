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

class TeamAccessValidator extends Base
{

    /**
     * @var TeamStruct|null
     */
    public ?TeamStruct $team = null;

    private ?int $idTeam = null;

    /**
     * Names the team to check when the route carries no id_team parameter, for instance when the team
     * is taken from the project a job belongs to.
     *
     * This says only *which* team is checked, never *whether* access is granted: membership below
     * remains the sole authorization. Pass a value derived on the server, never a request parameter,
     * or the CWE-639 hole described below reopens.
     */
    public function setIdTeam(int $idTeam): void
    {
        $this->idTeam = $idTeam;
    }

    public function _validate(): void
    {
        // Access is granted only to members of the team. The team is always resolved by the
        // requesting user's membership; the team_name request parameter is NOT an authorization
        // path (a name-based lookup would let any user read/act on any team — CWE-639 IDOR).
        $this->team = (new MembershipDao($this->controller->getDatabase()))->setCacheTTL(60 * 10)->findTeamByIdAndUser(
            $this->idTeam ?? $this->request->param('id_team'),
            $this->controller->getUser()
        );

        if (empty($this->team)) {
            throw new AuthorizationError("Not Authorized", 401);
        }

        if (method_exists($this->controller, 'setTeam')) {
            $this->controller->setTeam($this->team);
        }
    }

}