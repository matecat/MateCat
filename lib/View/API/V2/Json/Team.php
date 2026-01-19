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
use ReflectionException;
use Utils\Redis\RedisHandler;
use Utils\Tools\Utils;

class Team
{

    private ?array $data;

    public function __construct(?array $data = null)
    {
        $this->data = $data;
    }

    /**
     * @throws Exception
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
        $invitations = (new PendingInvitations((new RedisHandler())->getConnection(), []))->hasPendingInvitation((int)$team->id);

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
     * @return array
     * @throws ReflectionException
     * @throws Exception
     */
    public function render(?array $data = null): array
    {
        $out = [];

        if (empty($data)) {
            $data = $this->data;
        }

        /**
         * @var $data TeamStruct[]
         */
        foreach ($data as $team) {
            $out[] = $this->renderItem($team);
        }

        return $out;
    }


}