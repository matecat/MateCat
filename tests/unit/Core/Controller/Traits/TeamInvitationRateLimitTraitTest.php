<?php

namespace Matecat\Core\Controller\Traits;

use Controller\Services\RateLimiterService;
use Controller\Traits\TeamInvitationRateLimitTrait;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Teams\TeamModel;
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

    public function isOverLimit(Response $response, ?UserStruct $user, string $route, int $emailCount = 1): bool
    {
        return $this->isOverInvitationRateLimit($response, $user, $route, $emailCount);
    }

    public static function maxEmails(): int
    {
        return self::MAX_INVITATION_EMAILS;
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
                InvitationRateLimitHost::maxEmails(),
                1
            )
            ->willReturn(null);

        $host = new InvitationRateLimitHost($limiter);
        $host->isOverLimit(new Response(), $this->userWithEmail('owner@example.org'), '/api/v2/teams/members');
    }

    /**
     * The budget is spent in emails, so a request carrying fifty addresses has to cost fifty and
     * not one. Counting calls made the real ceiling ten requests times fifty addresses.
     */
    #[Test]
    public function chargesTheWindowOnceForEveryEmailTheRequestWillSend(): void
    {
        $limiter = $this->createMock(RateLimiterService::class);
        $limiter->expects($this->once())
            ->method('checkAndIncrement')
            ->with(
                $this->isInstanceOf(Response::class),
                'owner@example.org',
                '/api/v2/teams/members',
                InvitationRateLimitHost::maxEmails(),
                50
            )
            ->willReturn(null);

        $host = new InvitationRateLimitHost($limiter);
        $host->isOverLimit(new Response(), $this->userWithEmail('owner@example.org'), '/api/v2/teams/members', 50);
    }

    /**
     * A request that invites nobody still costs one, so the number of calls stays bounded as it
     * was before the budget became an email budget.
     */
    #[Test]
    public function aRequestThatSendsNothingStillCostsOne(): void
    {
        $limiter = $this->createMock(RateLimiterService::class);
        $limiter->expects($this->once())
            ->method('checkAndIncrement')
            ->with(
                $this->isInstanceOf(Response::class),
                'owner@example.org',
                '/route',
                InvitationRateLimitHost::maxEmails(),
                0
            )
            ->willReturn(null);

        $host = new InvitationRateLimitHost($limiter);
        $host->isOverLimit(new Response(), $this->userWithEmail('owner@example.org'), '/route', 0);
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
                InvitationRateLimitHost::maxEmails(),
                1
            )
            ->willReturn(null);

        $host = new InvitationRateLimitHost($limiter);
        $host->isOverLimit(new Response(), null, '/route');
    }

    #[Test]
    public function theLimitIsSmallEnoughToBeUselessAsAMailingVolume(): void
    {
        // one hand-built team must always fit in a window, and a few of them must not add up to
        // a mailing: the old ceiling was ten requests of fifty addresses, five hundred messages
        $this->assertGreaterThanOrEqual(TeamModel::MAX_MEMBER_EMAILS, InvitationRateLimitHost::maxEmails());
        $this->assertLessThanOrEqual(TeamModel::MAX_MEMBER_EMAILS * 2, InvitationRateLimitHost::maxEmails());
    }

}
