<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/02/17
 * Time: 12.12
 *
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Exception;
use Model\Teams\PendingInvitations;
use Model\Teams\TeamDao;
use Model\Teams\TeamModel;
use Model\Teams\TeamStruct;
use ReflectionException;
use RuntimeException;
use Utils\Redis\RedisHandler;
use View\API\V2\Json\Membership;

class TeamMembersController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $this->appendValidator(new TeamAccessValidator($this));
    }

    /**
     * Get the team members list
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws Exception
     * @throws \TypeError
     */
    public function index(): void
    {
        /** @var TeamStruct $team */
        $team = (new TeamDao($this->db()))->setCacheTTL(60 * 60 * 24)->fetchById($this->request->param('id_team'), TeamStruct::class)
            ?? throw new \RuntimeException('Team not found');
        $teamModel = new TeamModel($team);
        $teamModel->updateMembersProjectsCount();

        $teamId = $team->id ?? throw new \RuntimeException('Team has no id');
        $pendingInvitation = new PendingInvitations((new RedisHandler())->getConnection(), ['team_id' => $teamId, 'email' => '']);
        $formatter = new Membership($team->getMembers());
        $this->response->json([
            'members' => $formatter->render(),
            'pending_invitations' => $pendingInvitation->hasPendingInvitation($teamId)
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws \TypeError
     */
    public function update(): void
    {
        $params = $this->request->paramsPost()->getIterator()->getArrayCopy();

        $params = filter_var_array($params, [
            'members' => [
                'filter' => FILTER_SANITIZE_EMAIL,
                'flags' => FILTER_REQUIRE_ARRAY
            ]
        ]);

        $teamStruct = (new TeamDao($this->db()))
                ->fetchById($this->request->param('id_team'), TeamStruct::class)
            ?? throw new \RuntimeException('Team not found');

        $model = new TeamModel($teamStruct);
        $model->setUser($this->user);
        $members = array_values(array_filter(
            is_array($params['members']) ? $params['members'] : [],
            'is_string'
        ));
        $model->addMemberEmails($members);
        $full_members_list = $model->updateMembers();

        $teamId = $teamStruct->id ?? throw new \RuntimeException('Team has no id');
        $pendingInvitation = new PendingInvitations((new RedisHandler())->getConnection(), ['team_id' => $teamId, 'email' => '']);
        $formatter = new Membership($full_members_list);

        $this->refreshClientSessionIfNotApi();

        $this->response->json([
            'members' => $formatter->render(),
            'pending_invitations' => $pendingInvitation->hasPendingInvitation($teamId)
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws \TypeError
     */
    public function delete(): void
    {
        $this->db()->begin();

        $teamStruct = (new TeamDao($this->db()))
                ->fetchById($this->request->param('id_team'), TeamStruct::class)
            ?? throw new \RuntimeException('Team not found');

        $model = new TeamModel($teamStruct);
        $model->removeMemberUids([$this->request->param('uid_member')]);
        $model->setUser($this->user);
        $membersList = $model->updateMembers();

        $teamId = $teamStruct->id ?? throw new \RuntimeException('Team has no id');
        $pendingInvitation = new PendingInvitations((new RedisHandler())->getConnection(), ['team_id' => $teamId, 'email' => '']);
        $formatter = new Membership($membersList);

        $this->refreshClientSessionIfNotApi();

        $this->response->json([
            'members' => $formatter->render(),
            'pending_invitations' => $pendingInvitation->hasPendingInvitation($teamId)
        ]);
    }


}