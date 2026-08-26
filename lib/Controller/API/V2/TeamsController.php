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
        // Check what the reader will end up seeing, not what was typed. A mail client turns
        // entity text back into characters with its HTML parser, so without decoding first
        // "evil&#46;com" would satisfy the rules below and still arrive as a clickable
        // "evil.com". {@see EmailValue} decodes before writing for the same reason; this check
        // decodes on its own so the rule holds whatever the output path does.
        $decoded = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // The cap counts what the reader sees for the same reason: measured on the raw string, a
        // name of some sixty visible characters written with entities was rejected for length.
        // The raw form is what gets stored, so it is bounded separately by the column width.
        if (mb_strlen($name) > self::NAME_MAX_STORED_LENGTH) {
            throw new InvalidArgumentException(
                "Wrong parameter: name must be at most " . self::NAME_MAX_STORED_LENGTH . " characters",
                400
            );
        }

        if (mb_strlen($decoded) > self::NAME_MAX_LENGTH) {
            throw new InvalidArgumentException(
                "Wrong parameter: name must be at most " . self::NAME_MAX_LENGTH . " characters",
                400
            );
        }

        // A scheme ("https://", "javascript:") or a "www." prefix. No legitimate team name carries
        // one, so this costs nobody anything and stops the only shape that is unambiguously an
        // address rather than a word with a dot in it.
        //
        // A bare hostname used to be rejected here too, and is not any more. Measured against
        // production on 2026-08-13, that rule refused 120 stored names of which 14 were attacks and
        // 106 were real: customers name a team after their own domain, about twenty teams are named
        // after a member's address, and one of the refusals was this company's own name. Nothing
        // distinguishes "Alpha.Beta" from "evil.com" by shape, and a list of real top-level domains
        // does not help, because the legitimate names end in live suffixes too.
        //
        // What the rule was defending against was a mail client turning the name into a clickable
        // link in an invitation. That is now handled where it happens, by
        // {@see \Utils\Email\LinkDefanger}, which rewrites "evil.com" as "evil[.]com" in every email
        // — including for the names already stored, which a write-time rule could never reach.
        // `!== 0` rather than `=== 1`: preg_match returns false when PCRE gives up — a backtrack or
        // JIT stack limit — and a check whose job is to refuse must refuse when it cannot decide.
        // Only an explicit 0, the engine having looked and found nothing, is a pass. Unreachable
        // today because the length caps above run first and leave at most a hundred characters
        // here, but that is an ordering nobody should have to preserve to keep this safe.
        if (preg_match('~[a-z][a-z0-9+.-]*://|\bwww\.~i', $decoded) !== 0) {
            throw new InvalidArgumentException(
                "Wrong parameter: name cannot contain a URL",
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