<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 22/02/17
 * Time: 15.58
 *
 */

namespace Organizations;


use Predis\Client;

class PendingInvitations {

    const REDIS_INVITATIONS_SET = 'orgs_invites:%u';

    /**
     * @var Client
     */
    protected $redisClient;

    protected $payload;

    public function __construct( Client $redis, $payload ) {
        $this->redisClient = $redis;
        $this->payload     = $payload;
    }

    public function set(){

        $this->redisClient->sadd( sprintf( self::REDIS_INVITATIONS_SET, $this->payload[ 'organization_id' ] ), $this->payload[ 'email' ] );
        $this->redisClient->expire( sprintf( self::REDIS_INVITATIONS_SET, $this->payload[ 'organization_id' ] ), 60 * 60 * 24 * 3 ); //3 days renew

    }

    public function remove(){

        $this->redisClient->srem( sprintf( self::REDIS_INVITATIONS_SET, $this->payload[ 'organization_id' ] ), $this->payload[ 'email' ] );

    }

    public function get( $id_organization ){

        $this->redisClient->smembers( sprintf( self::REDIS_INVITATIONS_SET, $id_organization ) );

    }

}