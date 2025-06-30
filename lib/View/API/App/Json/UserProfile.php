<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 05/09/23
 * Time: 12:09
 *
 */

namespace View\API\App\Json;

use Model\ConnectedServices\ConnectedServiceStruct;
use Model\Teams\TeamStruct;
use Users_UserStruct;
use View\API\V2\Json\Team;
use View\API\V2\Json\User;

class UserProfile {

    /**
     * @param Users_UserStruct              $user
     * @param TeamStruct[]                  $teams
     * @param ConnectedServiceStruct[]|null $servicesStruct
     * @param array                         $userMetadata
     *
     * @return array
     */
    public function renderItem( Users_UserStruct $user, array $teams, array $servicesStruct = null, array $userMetadata = [] ) {

        return [
                'user'               => User::renderItem( $user ),
                'connected_services' => ( new ConnectedService( $servicesStruct ) )->render(),
                'teams'              => ( new Team() )->render( $teams ),
                'metadata'           => ( empty( $userMetadata ) ? null : $userMetadata ),
        ];

    }

}