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
use Model\Users\UserStruct;
use ReflectionException;
use View\API\V2\Json\Team;
use View\API\V2\Json\User;

class UserProfile
{

    /**
     * @param UserStruct $user
     * @param TeamStruct[] $teams
     * @param ConnectedServiceStruct[]|null $servicesStruct
     * @param array $userMetadata
     *
     * @return array
     * @throws ReflectionException
     * @throws EnvironmentIsBrokenException
     */
    public function renderItem(UserStruct $user, array $teams, array $servicesStruct = null, array $userMetadata = []): array
    {
        return [
            'user' => User::renderItem($user),
            'connected_services' => (new ConnectedService($servicesStruct))->render(),
            'teams' => (new Team())->render($teams),
            'metadata' => (empty($userMetadata) ? null : $userMetadata),
        ];
    }

}