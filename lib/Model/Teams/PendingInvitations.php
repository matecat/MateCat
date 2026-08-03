<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 22/02/17
 * Time: 15.58
 *
 */

namespace Model\Teams;


use Predis\ClientInterface;

class PendingInvitations
{

    const string REDIS_INVITATIONS_SET = 'teams_invites:%u';

    protected ClientInterface $redisClient;

    /** @var array{team_id: int, email: string} */
    protected array $payload;

    /**
     * @param ClientInterface $redis
     * @param array{team_id: int, email: string} $payload
     */
    public function __construct(ClientInterface $redis, array $payload)
    {
        $this->redisClient = $redis;
        $this->payload = $payload;
    }

    public function set(): void
    {
        $this->redisClient->sadd(sprintf(self::REDIS_INVITATIONS_SET, $this->payload['team_id']), [$this->payload['email']]);
        $this->redisClient->expire(sprintf(self::REDIS_INVITATIONS_SET, $this->payload['team_id']), 60 * 60 * 24 * 3); //3-day renew

    }

    public function remove(): int
    {
        return $this->redisClient->srem(sprintf(self::REDIS_INVITATIONS_SET, $this->payload['team_id']), $this->payload['email']);
    }

    /**
     * Is *this* invitation — this team and this email — still pending?
     *
     * Distinct from {@see listPendingInvitations()} on purpose, and the distinction is the point.
     * Deciding whether one invitation may be acted on by reading the whole set and testing it for
     * emptiness answers a different question: "does anybody at all have an open invitation to this
     * team?" Under that test, withdrawing one person's invitation had no effect for as long as any
     * other invitation to the same team stayed open — their link kept working. This asks about the
     * member.
     *
     * The comparison is exact, and safe to be: the same _getInvitedEmails() loop that sends the
     * invitation email mints the link's JWT and adds the address here, in one request, so the string
     * in the token and the string in the set are the same bytes.
     */
    public function isPending(): bool
    {
        return (bool)$this->redisClient->sismember(
            sprintf(self::REDIS_INVITATIONS_SET, $this->payload['team_id']),
            $this->payload['email']
        );
    }

    /**
     * Every address with an open invitation to a team, for display.
     *
     * Named for what it returns. It was called hasPendingInvitation(), which reads as a question about
     * one invitation and answers with a set — and that is exactly how it came to be used as an
     * authorisation check.
     *
     * @return array<string>
     */
    public function listPendingInvitations(int $id_team): array
    {
        return $this->redisClient->smembers(sprintf(self::REDIS_INVITATIONS_SET, $id_team));
    }

}