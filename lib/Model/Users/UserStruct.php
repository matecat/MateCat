<?php

namespace Model\Users;

use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use Model\ConnectedServices\Oauth\OauthTokenEncryption;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use ReflectionException;
use RuntimeException;
use stdClass;
use TypeError;
use Utils\Tools\Utils;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/04/15
 * Time: 12.54
 */
class UserStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    /**
     * Length of the random part, sized so that a two-character scope marker plus the secret exactly
     * fills varchar(50).
     *
     * 48 base62 characters is roughly 286 bits. There is no headroom left in the column: a longer
     * marker without a matching reduction here would be truncated on write, silently, and every
     * lookup would miss from then on.
     */
    private const int AUTH_TOKEN_RANDOM_LENGTH = 48;

    public ?int $uid = null;
    public ?string $email = null;
    public ?string $create_date = null;
    public ?string $first_name = null;
    public ?string $last_name = null;
    public ?string $salt = null;
    public ?string $pass = null;
    public ?string $oauth_access_token = null;
    public ?string $email_confirmed_at = null;
    public ?string $confirmation_token = null;
    public ?string $confirmation_token_created_at = null;

    /**
     * @return bool
     */
    public function isAnonymous(): bool
    {
        return !$this->isLogged();
    }

    /**
     * Identity only: a uid and an email are what identify an account.
     *
     * A first and last name were once required here too, which locked out every account holding blank
     * ones — legacy rows and provider signups among them. The login call answered 200 and set the
     * cookie, then each authenticated request that followed was rejected as anonymous, so the browser
     * bounced back to the login form and the account looked broken from the outside.
     *
     * A display name is profile data, and profile data does not decide whether someone is
     * authenticated. Do not add the name fields back: {@see UserStructTest::isLoggedIgnoresBlankNames}
     * guards this, because tests that populate every field or none at all cannot tell the two
     * behaviours apart.
     *
     * @return bool
     */
    public function isLogged(): bool
    {
        return !empty($this->uid) && !empty($this->email);
    }

    public function clearAuthToken(): void
    {
        $this->confirmation_token = null;
        $this->confirmation_token_created_at = null;
    }

    /**
     * Mints a token for one flow. The scope marker is stored with it, so it cannot be spent elsewhere.
     */
    public function initAuthToken(AuthTokenScope $scope): void
    {
        $this->confirmation_token = $scope->marker() . Utils::randomString(self::AUTH_TOKEN_RANDOM_LENGTH);
        $this->confirmation_token_created_at = Utils::mysqlTimestamp(time());
    }

    /**
     * The token as it travels in a link: the scope marker belongs in the database, not in the URL.
     *
     * Each flow prepends its own marker before looking a token up, which is what makes presenting one
     * to the wrong endpoint miss. Callers therefore never need to know a marker exists.
     *
     * A token carrying no recognised marker is returned unchanged — those were issued before scoping
     * and may still be in flight.
     */
    public function authTokenForUrl(): string
    {
        $token = $this->confirmation_token ?? '';

        foreach (AuthTokenScope::cases() as $scope) {
            if (str_starts_with($token, $scope->marker())) {
                return substr($token, strlen($scope->marker()));
            }
        }

        return $token;
    }

    /**
     * Keeps the current auth token when it is still usable, and mints one otherwise.
     *
     * {@see initAuthToken()} replaces the token unconditionally, which means a caller who only knows
     * an address can retire a link already sitting in that mailbox simply by asking for another one.
     * Handing back the token that is already in flight removes that: a repeated request re-sends the
     * same link instead of replacing it.
     *
     * The timestamp is deliberately left alone on reuse. Refreshing it would let repeated requests
     * slide the expiry forward without limit, which defeats the point of having a lifetime.
     *
     * Only a token of the same scope is reused. A pending token belonging to the other flow is
     * replaced, because one column holds one token — so cross-flow churn remains possible, and cannot
     * be removed without a second slot to keep both in. Same-flow requests, which are the ones an
     * anonymous caller can trigger repeatedly, no longer churn.
     *
     * @return bool true when a new token was minted, false when the existing one was kept
     */
    public function initAuthTokenIfStale(AuthTokenScope $scope): bool
    {
        if (
            $this->confirmation_token !== null
            && str_starts_with($this->confirmation_token, $scope->marker())
            && $this->confirmation_token_created_at !== null
            && strtotime($this->confirmation_token_created_at) >= time() - $scope->ttlSeconds()
        ) {
            return false;
        }

        $this->initAuthToken($scope);

        return true;
    }

    public static function getStruct(): UserStruct
    {
        return new UserStruct();
    }

    public function everSignedIn(): bool
    {
        return !(is_null($this->email_confirmed_at) && is_null($this->oauth_access_token));
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function shortName(): string
    {
        return trim(mb_substr($this->first_name ?? '', 0, 1) . mb_substr($this->last_name ?? '', 0, 1));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @return ?int
     */
    public function getUid(): ?int
    {
        return $this->uid;
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->last_name;
    }

     /**
      * @return TeamStruct
      * @throws ReflectionException
      * @throws Exception
      */
     public function getPersonalTeam(TeamDao $teamDao): TeamStruct
    {
        $teamDao->setCacheTTL(60 * 60 * 24);

        return $teamDao->getPersonalByUser($this);
    }

     /**
      * @return TeamStruct[]|null
      * @throws ReflectionException
      * @throws Exception
      */
     public function getUserTeams(MembershipDao $membershipDao): ?array
    {
        $membershipDao->setCacheTTL(60 * 60 * 24);

        return $membershipDao->findUserTeams($this);
    }

     /**
      * @return bool
      * @throws ReflectionException
      * @throws Exception
      */
     public function belongsToTeam(int $teamId, MembershipDao $membershipDao): bool
    {
        foreach ($this->getUserTeams($membershipDao) ?? [] as $team) {
            if ($team->id === $teamId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     * @throws RuntimeException
     */
    public function getMetadataAsKeyValue(MetadataDao $metadataDao): array
    {
        if ($this->uid === null) {
            throw new RuntimeException('User uid must be set before reading metadata');
        }

        $dao = $metadataDao;
        $collection = $dao->getAllByUid($this->uid);
        $data = [];

        foreach ($collection as $record) {
            $data[$record->key] = $record->getValue();
        }

        $mandatory = [
            'dictation' => 0,
            'show_whitespace' => 0,
            'guess_tags' => 1,
            'lexiqa' => 1,
            'character_counter' => 0,
            'ai_assistant' => 0,
            'cross_language_matches' => new stdClass(),
        ];

        foreach ($mandatory as $key => $value) {
            if (!isset($data[$key])) {
                $data[$key] = is_numeric($value) ? (int)$value : $value;
            }
        }

        return $data;
    }

    /**
     * Returns true if password matches
     *
     * An account created through an external provider has neither salt nor password: both columns
     * stay NULL because there has never been a password to store. That is an ordinary state rather
     * than a broken row, so it reads as "this password does not match" — every caller has to be able
     * to treat the answer as a plain yes or no, indistinguishable from a wrong password.
     *
     * An empty salt is left alone: those accounts do have a password, hashed against that empty
     * value, and it still has to verify.
     *
     * @return bool
     */
    public function passwordMatch(string $password): bool
    {
        if ($this->salt === null || $this->pass === null) {
            return false;
        }

        return Utils::verifyPass($password, $this->salt, $this->pass);
    }

    /**
     * Re-hashes an already verified password against a freshly minted salt, when the stored one is
     * empty.
     *
     * An empty salt means the hash was built over the password and nothing else, leaving the global
     * pepper as the only per-account variation — the point of a per-user salt is lost. Repairing it
     * needs the plaintext, which the application only holds while a login or password change is being
     * processed, so those are the only moments the row can be corrected without asking the owner for
     * anything.
     *
     * Callers must have verified $verifiedPassword first, and must persist salt and pass afterwards.
     * Returns true when the struct was modified.
     *
     * @return bool
     */
    public function rotateEmptySalt(string $verifiedPassword): bool
    {
        if ($this->salt !== '') {
            return false;
        }

        $this->salt = Utils::randomString(32);
        $this->pass = Utils::encryptPass($verifiedPassword, $this->salt);

        return true;
    }

    /**
     * Returns the decoded access token.
     *
     * @return null|string
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     * @throws TypeError
     */
    public function getDecryptedOauthAccessToken(): ?string
    {
        if ($this->oauth_access_token === null) {
            return null;
        }

        return OauthTokenEncryption::getInstance()->decrypt($this->oauth_access_token);
    }

    /**
     * @param string|null $field
     *
     * @return mixed
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     * @throws TypeError
     */
    public function getDecodedOauthAccessToken(?string $field = null): mixed
    {
        $decrypted = $this->getDecryptedOauthAccessToken();
        if ($decrypted === null) {
            return null;
        }

        $decoded = json_decode($decrypted, true);

        if ($field) {
            if (array_key_exists($field, $decoded)) {
                return $decoded[$field];
            } else {
                throw new Exception('key not found on token: ' . $field);
            }
        }

        return $decoded;
    }

}
