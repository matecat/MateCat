<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 05/09/23
 * Time: 12:09
 *
 */

namespace API\App\Json;

use API\V2\Json\Team;
use API\V2\Json\User;
use ConnectedServices\ConnectedServiceStruct;
use Teams\TeamStruct;
use Users_UserStruct;

class UserProfile {

    /**
     * @param Users_UserStruct              $user
     * @param TeamStruct[]                         $teams
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