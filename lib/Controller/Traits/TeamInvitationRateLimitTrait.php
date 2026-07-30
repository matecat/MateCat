<?php

namespace Controller\Traits;

use Controller\Services\RateLimiterService;
use Exception;
use Klein\Response;
use Model\Users\UserStruct;
use Utils\Tools\Utils;

/**
 * Rate limit for the endpoints that make MateCat send team invitations.
 *
 * Adding a member emails whatever address the caller supplies, from MateCat's domain,
 * quoting a team name the caller also controls. Nothing bounded how often that could be
 * done, so the invitation flow could be driven as a delivery channel for arbitrary text.
 * The per-request cap in {@see \Model\Teams\TeamModel::MAX_MEMBER_EMAILS} bounds one
 * call; this bounds how many calls.
 */
trait TeamInvitationRateLimitTrait
{

    use RateLimiterTrait;

    /**
     * Requests allowed per counter window. Comfortable for building a real team by hand,
     * useless as a mailing volume.
     */
    protected const int MAX_INVITATION_REQUESTS = 10;

    /**
     * Injection seam: tests supply a service backed by a fake Redis client.
     */
    protected ?RateLimiterService $invitationRateLimiter = null;

    /**
     * @return bool true when the caller is over the limit, in which case the 429 has
     *              already been placed on the response and the action must return.
     *
     * @throws Exception
     */
    protected function isOverInvitationRateLimit(Response $response, ?UserStruct $user, string $route): bool
    {
        // The account is the thing being limited; the address is only a fallback so an
        // unresolved user cannot skip the counter altogether.
        $identifier = $user->email ?? Utils::getRealIpAddr() ?? '127.0.0.1';

        $limited = $this->checkAndIncrementRateLimit(
            $response,
            $identifier,
            $route,
            self::MAX_INVITATION_REQUESTS,
            $this->invitationRateLimiter
        );

        return $limited instanceof Response;
    }

}
