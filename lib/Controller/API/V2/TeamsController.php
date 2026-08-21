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
use Controller\Traits\TeamInvitationRateLimitTrait;
use Exception;
use InvalidArgumentException;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Teams\TeamModel;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use Predis\ClientInterface;
use ReflectionException;
use Throwable;
use Utils\Constants\Teams;
use Utils\Redis\RedisHandler;
use Utils\Validation\UserSuppliedName;
use View\API\V2\Json\Team;

class TeamsController extends KleinController
{

    use TeamInvitationRateLimitTrait;

    private const int NAME_MAX_LENGTH = 100;

    /** `teams`.`name` is a varchar(255), and the name is stored as it was typed. */
    private const int NAME_MAX_STORED_LENGTH = 255;

    private ?ClientInterface $redis = null;

    /**
     * One Redis connection for the whole request, shared by every team rendered in it. Team::render()
     * needs a connection for the pending-invitation lookup, and it used to open its own per team.
     *
     * @throws Exception
     */
    private function redisConnection(): ClientInterface
    {
        return $this->redis ??= (new RedisHandler())->getConnection();
    }

    /**
     * Normalise a team name on the way in, and refuse one that reads as a link.
     *
     * Both halves live in {@see UserSuppliedName}, which every hand-typed name in MateCat now goes
     * through — the rules a team name needed turned out to be the rules all of them needed, and
     * there were five incompatible sanitizers doing the job before. The team name is the one that
     * gets the URL rule: it is quoted back in the invitation email MateCat sends, on the team
     * owner's behalf, to any address that owner types in, so it carries someone else's URL into a
     * message wearing MateCat's domain and signature.
     *
     * @throws InvalidArgumentException
     */
    private function validateTeamName(?string $raw): string
    {
        return UserSuppliedName::validatedForEmailQuote($raw, 'name', self::NAME_MAX_STORED_LENGTH, self::NAME_MAX_LENGTH);
    }

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
            // The name is stored as the user typed it and escaped by each output instead.
            // Encoding it here put entity text in the column, which a JavaScript string or a
            // JSON response can never decode back, so names containing & < > displayed wrong
            // everywhere. sanitizeTeamName() below does the part that must happen on the way in.
            'name' => [
                'filter' => FILTER_UNSAFE_RAW
            ],
            'type' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS
            ],
            'members' => [
                'filter' => FILTER_SANITIZE_EMAIL,
                'flags' => FILTER_REQUIRE_ARRAY
            ]
        ]);

        $params['name'] = $this->validateTeamName(is_string($params['name']) ? $params['name'] : null);

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
        $memberEmails = array_values(array_filter(
            is_array($params['members']) ? $params['members'] : [],
            'is_string'
        ));
        foreach ($memberEmails as $email) {
            $model->addMemberEmail($email);
        }
        $model->setUser($this->user);

        // creating a team also invites every member passed with it
        if ($this->isOverInvitationRateLimit($this->response, $this->user, '/api/v2/teams', count($memberEmails))) {
            return;
        }

        $team = $model->create();
        $formatted = new Team($userDao, $this->redisConnection());

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
            // The name is stored as the user typed it and escaped by each output instead.
            // Encoding it here put entity text in the column, which a JavaScript string or a
            // JSON response can never decode back, so names containing & < > displayed wrong
            // everywhere. sanitizeTeamName() below does the part that must happen on the way in.
            'name' => [
                'filter' => FILTER_UNSAFE_RAW
            ],
            'id_team' => [
                'filter' => FILTER_VALIDATE_INT
            ],
        ]);

        $teamId = is_int($params['id_team']) ? $params['id_team'] : throw new InvalidArgumentException("Wrong parameter: id_team is invalid", 400);

        $org = new TeamStruct();
        $org->id = $teamId;
        $name = $this->validateTeamName(is_string($params['name']) ? $params['name'] : null);
        $org->name = $name;

        $membershipDao = new MembershipDao($this->getDatabase());
        $org = $membershipDao->findTeamByIdAndUser($teamId, $this->user);

        if (empty($org)) {
            throw new AuthorizationError("Not Authorized", 401);
        }

        $org->name = $name;

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

        $formatted = new Team($userDao, $this->redisConnection(), [$org]);

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
        $formatted = new Team(new UserDao($this->getDatabase()), $this->redisConnection(), $teamList);
        $this->response->json(['teams' => $formatted->render()]);
    }

}