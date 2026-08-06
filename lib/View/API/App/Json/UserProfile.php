<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 05/09/23
 * Time: 12:09
 *
 */

namespace View\API\App\Json;

use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use Predis\ClientInterface;
use ReflectionException;
use TypeError;
use View\API\V2\Json\Team;
use View\API\V2\Json\User;

class UserProfile
{

    /**
     * @param UserStruct $user
     * @param TeamStruct[] $teams
     * @param UserDao $userDao
     * @param ClientInterface $redis Threaded down to Team so one connection serves every rendered
     *        team, instead of one per team.
     * @param ConnectedServiceStruct[]|null $servicesStruct
     * @param array<string, mixed> $userMetadata
     *
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws EnvironmentIsBrokenException
     * @throws \Exception
     * @throws TypeError
     */
    public function renderItem(
        UserStruct $user,
        array $teams,
        UserDao $userDao,
        ClientInterface $redis,
        ?array $servicesStruct = null,
        array $userMetadata = []
    ): array {
        return [
            'user' => User::renderItem($user),
            'connected_services' => (new ConnectedService($servicesStruct ?? []))->render(),
            'teams' => (new Team($userDao, $redis))->render($teams),
            'metadata' => (empty($userMetadata) ? null : $userMetadata),
        ];
    }

}