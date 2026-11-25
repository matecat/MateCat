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
     * Emails allowed per counter window. Counting requests instead made the real ceiling the
     * product of two numbers that were never multiplied together: ten calls each carrying up to
     * {@see \Model\Teams\TeamModel::MAX_MEMBER_EMAILS} addresses is five hundred messages, while
     * the limit read as ten. This is the budget in the unit that is actually being spent, and it
     * still covers two full batches per window — comfortable for building a real team by hand,
     * useless as a mailing volume.
     */
    protected const int MAX_INVITATION_EMAILS = 100;

    /**
     * Injection seam: tests supply a service backed by a fake Redis client.
     */
    protected ?RateLimiterService $invitationRateLimiter = null;

    /**
     * @param int $emailCount how many addresses this request will actually be asked to write to.
     *                        A request that invites nobody still costs one, so the number of calls
     *                        stays bounded as it was before the budget became an email budget.
     *
     * @return bool true when the caller is over the limit, in which case the 429 has
     *              already been placed on the response and the action must return.
     *
     * @throws Exception
     */
    protected function isOverInvitationRateLimit(Response $response, ?UserStruct $user, string $route, int $emailCount = 1): bool
    {
        // The account is the thing being limited; the address is only a fallback so an
        // unresolved user cannot skip the counter altogether.
        $identifier = $user->email ?? Utils::getRealIpAddr() ?? '127.0.0.1';

        $limited = $this->checkAndIncrementRateLimit(
            $response,
            $identifier,
            $route,
            self::MAX_INVITATION_EMAILS,
            $this->invitationRateLimiter,
            $emailCount
        );

        return $limited instanceof Response;
    }

}
