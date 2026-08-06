<?php

namespace Matecat\Core\Controller\Traits;

use Controller\Services\RateLimiterService;
use Controller\Traits\TeamInvitationRateLimitTrait;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;

/**
 * Exposes the trait's protected surface so the guard can be exercised on its own, with a
 * stubbed limiter instead of Redis.
 */
class InvitationRateLimitHost
{
    use TeamInvitationRateLimitTrait;

    public function __construct(?RateLimiterService $limiter)
    {
        $this->invitationRateLimiter = $limiter;
    }

    public function isOverLimit(Response $response, ?UserStruct $user, string $route): bool
    {
        return $this->isOverInvitationRateLimit($response, $user, $route);
    }

    public static function maxRequests(): int
    {
        return self::MAX_INVITATION_REQUESTS;
    }
}

class TeamInvitationRateLimitTraitTest extends AbstractTest
{

    private function userWithEmail(?string $email): UserStruct
    {
        $user = new UserStruct();
        $user->email = $email;

        return $user;
    }

    #[Test]
    public function reportsNotOverTheLimitWhenTheLimiterAllowsTheCall(): void
    {
        $limiter = $this->createStub(RateLimiterService::class);
        $limiter->method('checkAndIncrement')->willReturn(null);

        $host = new InvitationRateLimitHost($limiter);

        $this->assertFalse($host->isOverLimit(new Response(), $this->userWithEmail('a@example.org'), '/route'));
    }

    #[Test]
    public function reportsOverTheLimitWhenTheLimiterRefusesTheCall(): void
    {
        $limiter = $this->createStub(RateLimiterService::class);
        $limiter->method('checkAndIncrement')->willReturn(new Response());

        $host = new InvitationRateLimitHost($limiter);

        $this->assertTrue($host->isOverLimit(new Response(), $this->userWithEmail('a@example.org'), '/route'));
    }

    /**
     * The account is what gets limited, so the counter has to be keyed on the caller's
     * address and not on something shared such as the route alone.
     */
    #[Test]
    public function countsAgainstTheCallersEmailAndTheGivenRoute(): void
    {
        $limiter = $this->createMock(RateLimiterService::class);
        $limiter->expects($this->once())
            ->method('checkAndIncrement')
            ->with(
                $this->isInstanceOf(Response::class),
                'owner@example.org',
                '/api/v2/teams/members',
                InvitationRateLimitHost::maxRequests()
            )
            ->willReturn(null);

        $host = new InvitationRateLimitHost($limiter);
        $host->isOverLimit(new Response(), $this->userWithEmail('owner@example.org'), '/api/v2/teams/members');
    }

    /**
     * An unresolved user must still be counted, otherwise the guard could be skipped
     * outright rather than merely keyed differently.
     */
    #[Test]
    public function stillCountsWhenNoUserEmailIsAvailable(): void
    {
        $limiter = $this->createMock(RateLimiterService::class);
        $limiter->expects($this->once())
            ->method('checkAndIncrement')
            ->with(
                $this->isInstanceOf(Response::class),
                $this->logicalAnd($this->isString(), $this->logicalNot($this->equalTo(''))),
                '/route',
                InvitationRateLimitHost::maxRequests()
            )
            ->willReturn(null);

        $host = new InvitationRateLimitHost($limiter);
        $host->isOverLimit(new Response(), null, '/route');
    }

    #[Test]
    public function theLimitIsSmallEnoughToBeUselessAsAMailingVolume(): void
    {
        $this->assertLessThanOrEqual(20, InvitationRateLimitHost::maxRequests());
        $this->assertGreaterThan(1, InvitationRateLimitHost::maxRequests());
    }

}
