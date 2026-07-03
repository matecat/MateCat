<?php


namespace Matecat\Core\Model\Teams;

use Matecat\TestHelpers\AbstractTest;
use Model\Teams\PendingInvitations;
use PHPUnit\Framework\Attributes\Test;
use Predis\ClientInterface;
use Predis\Command\CommandInterface;

class PendingInvitationsTest extends AbstractTest
{
    private function makeRedisStub(array $smembersResult = []): ClientInterface
    {
        return new class($smembersResult) implements ClientInterface {
            private array $smembersResult;
            private array $calls = [];

            public function __construct(array $smembersResult)
            {
                $this->smembersResult = $smembersResult;
            }

            public function getCalls(): array
            {
                return $this->calls;
            }

            public function getCommandFactory()
            {
                return null;
            }

            public function getOptions()
            {
                return null;
            }

            public function connect()
            {
            }

            public function disconnect()
            {
            }

            public function getConnection()
            {
                return null;
            }

            public function createCommand($method, $arguments = [])
            {
                return null;
            }

            public function executeCommand(CommandInterface $command)
            {
                return null;
            }

            public function __call($method, $arguments)
            {
                $this->calls[] = ['method' => $method, 'args' => $arguments];

                return match ($method) {
                    'sadd' => 1,
                    'expire' => true,
                    'srem' => 1,
                    'smembers' => $this->smembersResult,
                    default => null,
                };
            }
        };
    }

    #[Test]
    public function setAddsToRedisSet(): void
    {
        $redis = $this->makeRedisStub();
        $pending = new PendingInvitations($redis, ['team_id' => 5, 'email' => 'user@example.com']);
        $pending->set();

        $calls = $redis->getCalls();
        $this->assertSame('sadd', $calls[0]['method']);
        $this->assertStringContains('teams_invites:5', $calls[0]['args'][0]);
    }

    #[Test]
    public function removeCallsSrem(): void
    {
        $redis = $this->makeRedisStub();
        $pending = new PendingInvitations($redis, ['team_id' => 5, 'email' => 'user@example.com']);
        $result = $pending->remove();

        $this->assertSame(1, $result);
    }

    #[Test]
    public function hasPendingInvitationReturnsMembers(): void
    {
        $redis = $this->makeRedisStub(['user@example.com']);
        $pending = new PendingInvitations($redis, ['team_id' => 5, 'email' => 'user@example.com']);
        $result = $pending->hasPendingInvitation(5);

        $this->assertSame(['user@example.com'], $result);
    }

    #[Test]
    public function hasPendingInvitationReturnsEmptyForNoInvitations(): void
    {
        $redis = $this->makeRedisStub([]);
        $pending = new PendingInvitations($redis, ['team_id' => 5, 'email' => 'user@example.com']);
        $result = $pending->hasPendingInvitation(5);

        $this->assertSame([], $result);
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(str_contains($haystack, $needle), "Expected '$haystack' to contain '$needle'");
    }
}
