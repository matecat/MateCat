<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/02/17
 * Time: 13.01
 *
 */

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Exception;
use InvalidArgumentException;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Teams\TeamModel;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use ReflectionException;
use Throwable;
use Utils\Constants\Teams;
use View\API\V2\Json\Team;

class TeamsController extends KleinController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    protected function addValidatorAccess(): void
    {
        $this->appendValidator(new TeamAccessValidator($this));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws \TypeError
     */
    public function create(): void
    {
        $params = $this->request->paramsPost()->getIterator()->getArrayCopy();

        $params = filter_var_array($params, [
            'name' => [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_STRIP_BACKTICK
            ],
            'type' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS
            ],
            'members' => [
                'filter' => FILTER_SANITIZE_EMAIL,
                'flags' => FILTER_REQUIRE_ARRAY
            ]
        ]);

        $params['name'] = trim($params['name']);

        if (empty($params['name'])) {
            throw new InvalidArgumentException("Wrong parameter: name is empty", 400);
        }

        if (empty($params['type'])) {
            throw new InvalidArgumentException("Wrong parameter: type is empty", 400);
        }

        if (!in_array($params['type'], [Teams::GENERAL, Teams::PERSONAL])) {
            throw new InvalidArgumentException("Wrong parameter: type is not allowed [Allowed values: personal, general]", 400);
        }

        $teamStruct = new TeamStruct([
            'created_by' => $this->user->uid,
            'name' => $params['name'],
            'type' => $params['type']
        ]);

        $userDao = new UserDao($this->getDatabase());
        $model = new TeamModel($teamStruct, $userDao, new TeamDao($this->getDatabase()));
        $memberEmails = is_array($params['members']) ? $params['members'] : [];
        foreach ($memberEmails as $email) {
            if (is_string($email)) {
                $model->addMemberEmail($email);
            }
        }
        $model->setUser($this->user);

        $team = $model->create();
        $formatted = new Team($userDao, null);

        $this->refreshClientSessionIfNotApi();

        $this->response->json(['team' => $formatted->renderItem($team)]);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws Throwable
     */
    public function update(): void
    {
        $this->addValidatorAccess();
        $this->validateRequest();

        // sanitize params
        $params = filter_var_array($this->params, [
            'name' => [
                'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_STRIP_BACKTICK
            ],
            'id_team' => [
                'filter' => FILTER_VALIDATE_INT
            ],
        ]);

        $teamId = is_int($params['id_team']) ? $params['id_team'] : throw new InvalidArgumentException("Wrong parameter: id_team is invalid", 400);

        $org = new TeamStruct();
        $org->id = $teamId;
        $org->name = trim($params['name']);

        if (empty($org->name)) {
            throw new InvalidArgumentException("Wrong parameter: name is empty", 400);
        }

        $membershipDao = new MembershipDao($this->getDatabase());
        $org = $membershipDao->findTeamByIdAndUser($teamId, $this->user);

        if (empty($org)) {
            throw new AuthorizationError("Not Authorized", 401);
        }

        $org->name = trim($params['name']);

        $teamDao = new TeamDao($this->getDatabase());

        $teamDao->updateTeamName($org);
        $orgId = $org->id ?? throw new \RuntimeException('Team has no id');
        $memberList = (new MembershipDao($this->getDatabase()))->getMemberListByTeamId($orgId);

        $userDao = new UserDao($this->getDatabase());
        foreach ($memberList as $user) {
            (new MembershipDao($this->getDatabase()))->destroyCacheUserTeams(
                $user->getUser($userDao)
            ); // clean the cache for all team users to see the changes
        }

        $formatted = new Team($userDao, [$org]);

        $this->refreshClientSessionIfNotApi();

        $this->response->json(['team' => $formatted->render()]);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws \TypeError
     */
    public function getTeamList(): void
    {
        $teamList = (new MembershipDao($this->getDatabase()))->findUserTeams($this->user);
        $formatted = new Team(new UserDao($this->getDatabase()), $teamList);
        $this->response->json(['teams' => $formatted->render()]);
    }

}