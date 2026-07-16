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
use ReflectionException;
use TypeError;
use View\API\V2\Json\Team;
use View\API\V2\Json\User;

class UserProfile
{

    /**
     * @param UserStruct $user
     * @param TeamStruct[] $teams
     * @param ConnectedServiceStruct[]|null $servicesStruct
     * @param array<string, mixed> $userMetadata
     * @param UserDao|null $userDao
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
        array $servicesStruct = null,
        array $userMetadata = [],
        ?UserDao $userDao = null
    ): array {
        $userDao = $userDao ?? throw new \InvalidArgumentException('UserDao is required');

        return [
            'user' => User::renderItem($user),
            'connected_services' => (new ConnectedService($servicesStruct ?? []))->render(),
            'teams' => (new Team($userDao, null))->render($teams),
            'metadata' => (empty($userMetadata) ? null : $userMetadata),
        ];
    }

}