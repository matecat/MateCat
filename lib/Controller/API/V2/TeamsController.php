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
use View\API\V2\Json\Team;

class TeamsController extends KleinController
{

    use TeamInvitationRateLimitTrait;

    private const int NAME_MAX_LENGTH = 100;

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
     * Normalise a team name on the way in.
     *
     * The name is no longer entity-encoded before it is stored, so the two things that
     * encoding used to take care of have to be done explicitly. Control and format
     * characters are removed: a name is a single line of text, and CR/LF in particular would
     * otherwise travel into the Subject header of the membership emails. Runs of whitespace
     * collapse so a name cannot be padded out to look like separate lines.
     *
     * Everything else is preserved verbatim; making the value safe for a given output is
     * that output's job.
     */
    private function sanitizeTeamName(?string $raw): string
    {
        $name = preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]+/u', ' ', $raw ?? '') ?? '';
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * Reject a team name that reads as a link.
     *
     * The name is quoted back in the invitation email MateCat sends, on the team owner's
     * behalf, to any address that owner types in. Mail clients auto-link bare URLs and
     * bare hostnames, so a name like "verify at example.com" needs no markup to become a
     * clickable link in a message carrying MateCat's domain and signature. Holding names
     * to plain text keeps that transactional email from carrying someone else's URL.
     *
     * @throws InvalidArgumentException
     */
    private function assertNameIsPlainText(string $name): void
    {
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidArgumentException(
                "Wrong parameter: name must be at most " . self::NAME_MAX_LENGTH . " characters",
                400
            );
        }

        // Check what the reader will end up seeing, not what was typed. The email templates
        // escape with double_encode: false so that names stored before names were kept as
        // typed still render correctly, which means entity text passes through to the
        // recipient and is turned back into characters by the mail client's HTML parser.
        // Without decoding first, "evil&#46;com" would satisfy the rules below and still
        // arrive as a clickable "evil.com".
        $decoded = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // a scheme ("https://", "javascript:") or a "www." prefix
        $hasUrlPrefix = preg_match('~[a-z][a-z0-9+.-]*://|\bwww\.~i', $decoded) === 1;
        // a bare hostname: one or more dot-separated labels ending in a letters-only TLD
        $hasHostname = preg_match('~(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}~i', $decoded) === 1;

        if ($hasUrlPrefix || $hasHostname) {
            throw new InvalidArgumentException(
                "Wrong parameter: name cannot contain a URL or a domain name",
                400
            );
        }
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

        $params['name'] = $this->sanitizeTeamName(is_string($params['name']) ? $params['name'] : null);

        if (empty($params['name'])) {
            throw new InvalidArgumentException("Wrong parameter: name is empty", 400);
        }

        $this->assertNameIsPlainText($params['name']);

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

        // creating a team also invites every member passed with it
        if ($this->isOverInvitationRateLimit($this->response, $this->user, '/api/v2/teams')) {
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
        $name = $this->sanitizeTeamName(is_string($params['name']) ? $params['name'] : null);
        $org->name = $name;

        if (empty($org->name)) {
            throw new InvalidArgumentException("Wrong parameter: name is empty", 400);
        }

        $this->assertNameIsPlainText($org->name);

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