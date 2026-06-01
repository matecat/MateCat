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
use Predis\ClientInterface;
use ReflectionException;
use TypeError;
use Utils\Redis\RedisHandler;
use Utils\Tools\Utils;

class Team
{

    /** @var TeamStruct[]|null */
    private ?array $data;

    /**
     * @param TeamStruct[]|null $data
     */
    public function __construct(?array $data = null)
    {
        $this->data = $data;
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
            $memberShipFormatter = new Membership($members);
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
            $this->createRedisConnection(),
            ['team_id' => $teamId, 'email' => '']
        ))->hasPendingInvitation($teamId);
    }

    /**
     * @throws Exception
     */
    protected function createRedisConnection(): ClientInterface
    {
        return (new RedisHandler())->getConnection();
    }


}