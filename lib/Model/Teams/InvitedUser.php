<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/02/17
 * Time: 15.05
 *
 */

namespace Model\Teams;


use Controller\Abstracts\FlashMessage;
use Controller\API\Commons\Exceptions\ValidationError;
use DomainException;
use Exception;
use Klein\Response;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use TypeError;
use UnexpectedValueException;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;

class InvitedUser
{

    /**
     * @var array<string, mixed>
     */
    protected array $jwt = [];

    protected ?Response $response;
    protected TeamDao $teamDao;
    protected UserDao $userDao;
    protected RedisHandler $redisHandler;

    /**
     * @param string $jwt
     * @param Response|null $response
     * @param TeamDao|null $teamDao
     * @param RedisHandler|null $redisHandler
     *
     * @throws ValidationError
     * @throws TypeError
     * @throws UnexpectedValueException
     * @throws Exception
     */
    public function __construct(
        string $jwt = '',
        ?Response $response = null,
        ?TeamDao $teamDao = null,
        ?RedisHandler $redisHandler = null,
        ?UserDao $userDao = null
    ) {
        if ($jwt !== '') {
            try {
                $this->jwt = SimpleJWT::getValidatedInstanceFromString(
                    $jwt,
                    AppConfig::$AUTHSECRET
                )->getPayload();
            } catch (DomainException $e) {
                throw new ValidationError($e->getMessage(), $e->getCode(), $e);
            }
        }

        $this->response = $response;
        $this->teamDao = $teamDao ?? throw new \InvalidArgumentException('TeamDao is required');
        $this->userDao = $userDao ?? throw new \InvalidArgumentException('UserDao is required');
        $this->redisHandler = $redisHandler ?? new RedisHandler();
    }

    /**
     * @throws Exception
     */
    public function prepareUserInvitedSignUpRedirect(): void
    {
        $_SESSION['invited_to_team'] = $this->jwt;
        FlashMessage::set('popup', 'signup', FlashMessage::SERVICE);
        FlashMessage::set('signup_email', $this->jwt['email'], FlashMessage::SERVICE);
    }

    /**
     * @param UserStruct $user
     * @param array{team_id: int, email: string} $invitation
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function completeTeamSignUp(UserStruct $user, array $invitation): void
    {
        $teamStruct = $this->teamDao->fetchById($invitation['team_id'], TeamStruct::class)
            ?? throw new RuntimeException('Team not found');

        $teamModel = new TeamModel($teamStruct, $this->userDao, $this->teamDao);
        $teamModel->setUser($user);
        $teamModel->addMemberEmail($invitation['email']);
        $teamModel->updateMembers();

        $pendingInvitation = new PendingInvitations($this->redisHandler->getConnection(), $invitation);
        $pendingInvitation->remove();

        unset($_SESSION['invited_to_team']);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function hasPendingInvitations(): bool
    {
        if (!isset($_SESSION['invited_to_team']) || empty($_SESSION['invited_to_team']['team_id'])) {
            return false;
        }

        $pendingInvitation = new PendingInvitations($this->redisHandler->getConnection(), $_SESSION['invited_to_team']);
        if (!$pendingInvitation->hasPendingInvitation($_SESSION['invited_to_team']['team_id'])) {
            return false;
        }

        return true;
    }

}
