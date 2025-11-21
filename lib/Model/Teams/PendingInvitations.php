<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 22/02/17
 * Time: 15.58
 *
 */

namespace Model\Teams;


use Predis\Client;

class PendingInvitations
{

    const string REDIS_INVITATIONS_SET = 'teams_invites:%u';

    /**
     * @var Client
     */
    protected Client $redisClient;

    protected array $payload;

    /**
     * @param Client                                     $redis
     * @param array{team_id?: int, email?: string} $payload
     */
    public function __construct(Client $redis, array $payload)
    {
        $this->redisClient = $redis;
        $this->payload     = $payload;
    }

    public function set(): void
    {
        $this->redisClient->sadd(sprintf(self::REDIS_INVITATIONS_SET, $this->payload[ 'team_id' ]), $this->payload[ 'email' ]);
        $this->redisClient->expire(sprintf(self::REDIS_INVITATIONS_SET, $this->payload[ 'team_id' ]), 60 * 60 * 24 * 3); //3-day renew

    }

    public function remove(): int
    {
        return $this->redisClient->srem(sprintf(self::REDIS_INVITATIONS_SET, $this->payload[ 'team_id' ]), $this->payload[ 'email' ]);
    }

    public function hasPendingInvitation($id_team): array
    {
        return $this->redisClient->smembers(sprintf(self::REDIS_INVITATIONS_SET, $id_team));
    }

}