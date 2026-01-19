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
use Model\Users\UserStruct;
use ReflectionException;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;

class InvitedUser
{

    /**
     * @var array
     */
    protected array $jwt = [];

    protected Response $response;

    /**
     * InvitedUser constructor.
     *
     * @param string $jwt
     * @param Response $response
     *
     * @throws ValidationError
     */
    public function __construct(string $jwt, Response $response)
    {
        try {
            $this->jwt = SimpleJWT::getValidatedInstanceFromString(
                $jwt,
                AppConfig::$AUTHSECRET
            )->getPayload();
        } catch (DomainException $e) {
            throw new ValidationError($e->getMessage(), $e->getCode(), $e);
        }

        $this->response = $response;
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
     * @param array $invitation
     *
     * @throws ReflectionException
     */
    public static function completeTeamSignUp(UserStruct $user, array $invitation): void
    {
        $teamStruct = (new TeamDao)->findById($invitation['team_id']);

        $teamModel = new TeamModel($teamStruct);
        $teamModel->setUser($user);
        $teamModel->addMemberEmail($invitation['email']);
        $teamModel->updateMembers();

        $pendingInvitation = new PendingInvitations((new RedisHandler())->getConnection(), $invitation);
        $pendingInvitation->remove(); // remove pending invitation

        unset($_SESSION['invited_to_team']);
    }

    /**
     * @throws ReflectionException
     */
    public static function hasPendingInvitations(): bool
    {
        if (!isset($_SESSION['invited_to_team']) || empty($_SESSION['invited_to_team']['team_id'])) { // check if this is the right session caller
            return false;
        }

        $pendingInvitation = new PendingInvitations((new RedisHandler())->getConnection(), $_SESSION['invited_to_team']);
        if (!$pendingInvitation->hasPendingInvitation($_SESSION['invited_to_team']['team_id'])) {
            return false; // pending invitation already accepted (one-time token consumed)
        }

        return true;
    }

}