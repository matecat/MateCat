<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 02/02/2017
 * Time: 17:36
 */

namespace View\API\V2\Json;


use Exception;
use Model\Teams\PendingInvitations;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use Predis\ClientInterface;
use ReflectionException;
use TypeError;
use Utils\Tools\Utils;

class Team
{

    /** @var TeamStruct[]|null */
    private ?array $data;

    private UserDao $userDao;

    private ClientInterface $redis;

    /**
     * @param UserDao $userDao
     * @param ClientInterface $redis One connection for the whole render. Previously each rendered
     *        team opened its own, since RedisHandler::getConnection() is per-instance — so a user in
     *        N teams cost N connections per payload.
     * @param TeamStruct[]|null $data
     */
    public function __construct(UserDao $userDao, ClientInterface $redis, ?array $data = null)
    {
        $this->data = $data;
        $this->userDao = $userDao;
        $this->redis = $redis;
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     * @throws TypeError
     */
    public function renderItem(TeamStruct $team): array
    {
        $row = [
            'id' => (int)$team->id,
            'name' => $team->name,
            'type' => $team->type,
            'created_at' => Utils::api_timestamp($team->created_at),
            'created_by' => $team->created_by
        ];

        $members = $team->getMembers();
        $invitations = $this->getPendingInvitations((int)$team->id);

        if (!empty($members)) {
            $memberShipFormatter = new Membership($members, $this->userDao);
            $row['members'] = $memberShipFormatter->render();
        }

        $row['pending_invitations'] = $invitations;

        return $row;
    }

    /**
     * @param TeamStruct[]|null $data
     *
     * @return array<int, array<string, mixed>>
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function render(?array $data = null): array
    {
        $out = [];

        if ($data === null) {
            $data = $this->data;
        }

        foreach ($data ?? [] as $team) {
            $out[] = $this->renderItem($team);
        }

        return $out;
    }

    /**
     * @return array<string>
     * @throws Exception
     */
    protected function getPendingInvitations(int $teamId): array
    {
        return (new PendingInvitations(
            $this->redis,
            ['team_id' => $teamId, 'email' => '']
        ))->listPendingInvitations($teamId);
    }

}