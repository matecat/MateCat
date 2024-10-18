<?php

namespace API\Commons\Authentication;

use API\App\Json\UserProfile;
use ApiKeys_ApiKeyDao;
use ApiKeys_ApiKeyStruct;
use ConnectedServices\ConnectedServiceDao;
use ReflectionException;
use TeamModel;
use Teams\MembershipDao;
use Teams\TeamStruct;
use Users_UserDao;
use Users_UserStruct;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 19/09/24
 * Time: 13:36
 *
 */
class AuthenticationHelper {

    private Users_UserStruct $user;
    /**
     * @var true
     */
    private bool                         $logged;
    private ?ApiKeys_ApiKeyStruct        $api_record = null;
    private array                        $session;
    private static ?AuthenticationHelper $instance   = null;

    /**
     * @throws ReflectionException
     */
    public static function getInstance( array &$session, ?string $api_key = null, ?string $api_secret = null ): AuthenticationHelper {
        if ( !self::$instance ) {
            self::$instance = new AuthenticationHelper( $session, $api_key, $api_secret );
        }

        return self::$instance;
    }

    /**
     * @param array       $session
     * @param string|null $api_key
     * @param string|null $api_secret
     *
     * @throws ReflectionException
     */
    protected function __construct( array &$session, ?string $api_key = null, ?string $api_secret = null ) {

        $this->session =& $session;
        $this->user    = new Users_UserStruct();

        if ( $this->validKeys( $api_key, $api_secret ) ) {
            $this->user = $this->api_record->getUser();
        } elseif ( !empty( $this->session[ 'user' ] ) && !empty( $this->session[ 'user_profile' ] ) ) {
            $this->user = $this->session[ 'user' ]; // php deserialize from session string
        } else {
            // Credentials from AuthCookie
            /**
             * @var $user Users_UserStruct
             */
            $user_cookie_credentials = AuthCookie::getCredentials();
            if ( !empty( $user_cookie_credentials ) && !empty( $user_cookie_credentials[ 'user' ] ) ) {
                $userDao = new Users_UserDao();
                $userDao->setCacheTTL( 60 * 60 );
                $this->user = $userDao->getByUid( $user_cookie_credentials[ 'user' ][ 'uid' ] );
                $this->setUserSession();
            }

        }

        $this->logged = (
                !empty( $this->user->uid ) &&
                !empty( $this->user->email ) &&
                !empty( $this->user->first_name ) &&
                !empty( $this->user->last_name )
        );

    }

    /**
     * @throws ReflectionException
     */
    public static function refreshSession( array &$session ){
        unset( $session[ 'user' ] );
        unset( $session[ 'user_profile' ] );
        self::$instance = new AuthenticationHelper( $session );
    }

    public static function destroyAuthentication( array &$session ){
        unset( $session[ 'user' ] );
        unset( $session[ 'user_profile' ] );
        AuthCookie::destroyAuthentication();
    }

    /**
     * @throws ReflectionException
     */
    protected function setUserSession() {
        $session_status = session_status();
        if ( $session_status == PHP_SESSION_ACTIVE ) {
            $this->session[ 'cid' ]          = $this->user->getEmail();
            $this->session[ 'uid' ]          = $this->user->getUid();
            $this->session[ 'user' ]         = $this->user;
            $this->session[ 'user_profile' ] = static::getUserProfile( $this->user );
        }
    }

    /**
     * @throws ReflectionException
     */
    protected static function getUserProfile( Users_UserStruct $user ): array {

        $metadata   = $user->getMetadataAsKeyValue();
        $membersDao = new MembershipDao();
        $membersDao->setCacheTTL( 60 * 5 );
        $userTeams = array_map(
                function ( $team ) use ( $membersDao ) {
                    $teamModel = new TeamModel( $team );
                    $teamModel->updateMembersProjectsCount();

                    /** @var $team TeamStruct */
                    return $team;
                },
                $membersDao->findUserTeams( $user )
        );

        $dao      = new ConnectedServiceDao();
        $services = $dao->findServicesByUser( $user );

        return ( new UserProfile() )->renderItem(
                $user,
                $userTeams,
                $services,
                $metadata
        );

    }

    /**
     * validKeys
     *
     * This was implemented to allow passing a pair of keys to identify the user, or to deny access.
     *
     * This function returns true if the keys are not provided.
     *
     * If keys are provided, it checks for them to be valid or return false.
     *
     */
    protected function validKeys( ?string $api_key = null, ?string $api_secret = null ): bool {

        if ( $api_key || $api_secret ) {
            $this->api_record = ApiKeys_ApiKeyDao::findByKey( $api_key );
            if ( $this->api_record ) {
                return $this->api_record->validSecret( $api_secret );
            }
        }

        return false;
    }

    public function getUser(): ?Users_UserStruct {
        return $this->user;
    }

    public function isLogged(): bool {
        return $this->logged;
    }

    public function getApiRecord(): ApiKeys_ApiKeyStruct {
        return $this->api_record;
    }

}