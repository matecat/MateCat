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
use Utils\Session\SessionStore;
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
    protected SessionStore $session;

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
        ?UserDao $userDao = null,
        ?SessionStore $session = null
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
        $this->session = $session ?? throw new \InvalidArgumentException('SessionStore is required');
        $this->redisHandler = $redisHandler ?? new RedisHandler();
    }

    /**
     * @throws Exception
     */
    public function prepareUserInvitedSignUpRedirect(): void
    {
        $this->session->set('invited_to_team', $this->jwt);

        $flash = new FlashMessage($this->session);
        $flash->set('popup', 'signup', FlashMessage::SERVICE);
        $flash->set('signup_email', $this->jwt['email'], FlashMessage::SERVICE);
    }

    /**
     * The invitation is read from the store rather than accepted as an argument.
     *
     * The caller used to re-read `$_SESSION['invited_to_team']` and hand it back after
     * hasPendingInvitations() had already validated the very same value — two reads of one key, only
     * one of them checked, and the unchecked one is the one that reached the team lookup. Reading it
     * here keeps the validation and the use in the same place.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws RuntimeException when no valid invitation is in the session
     */
    public function completeTeamSignUp(UserStruct $user): void
    {
        $invitation = $this->validatedInvitation()
            ?? throw new RuntimeException('No valid pending invitation in session');

        $teamStruct = $this->teamDao->fetchById($invitation['team_id'], TeamStruct::class)
            ?? throw new RuntimeException('Team not found');

        $teamModel = new TeamModel($teamStruct, $this->userDao, $this->teamDao);
        $teamModel->setUser($user);
        $teamModel->addMemberEmail($invitation['email']);
        $teamModel->updateMembers();

        $pendingInvitation = new PendingInvitations($this->redisHandler->getConnection(), $invitation);
        $pendingInvitation->remove();

        $this->session->remove('invited_to_team');
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function hasPendingInvitations(): bool
    {
        $invitation = $this->validatedInvitation();

        if ($invitation === null) {
            return false;
        }

        $pendingInvitation = new PendingInvitations($this->redisHandler->getConnection(), $invitation);

        // hasPendingInvitation() returns the redis set members, not a bool — keep the emptiness
        // check rather than returning it, which would widen this method's return type.
        return !empty($pendingInvitation->hasPendingInvitation($invitation['team_id']));
    }

    /**
     * The invitation in the session, or null when there is none that can be acted on.
     *
     * The payload comes from a JWT, so team_id may arrive as an int or as its decimal string.
     * Validating and rebuilding the array is what lets the shape be guaranteed honestly, without an
     * inline assertion standing in for a check.
     *
     * @return array{team_id: int, email: string}|null
     */
    private function validatedInvitation(): ?array
    {
        $invitation = $this->session->get('invited_to_team');

        if (!is_array($invitation)) {
            return null;
        }

        $teamId = $invitation['team_id'] ?? null;
        $email  = $invitation['email'] ?? null;

        if (!is_numeric($teamId) || (int)$teamId === 0 || !is_string($email)) {
            return null;
        }

        return ['team_id' => (int)$teamId, 'email' => $email];
    }

}
