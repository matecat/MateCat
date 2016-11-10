<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 15:28
 */

namespace ConnectedServices\GDrive;

use ConnectedServices\ConnectedServiceDao;
use OauthTokenEncryption ;

use Users_UserDao ;


class Session
{

    const SESSION_FILE_LIST = 'gdriveFileList';
    const SESSION_FILE_NAME = 'fileName';
    const SESSION_FILE_HASH = 'fileHash';

    protected  $session ;

    public function __construct( $session ) {
        if ( !isset( $session['uid'] )) {
            throw new \Exception('Cannot instantiate session for unlogged user') ;
        }

        $this->session = $session ;
    }

    public static function cleanupSessionFiles() {
        if( self::sessionHasFiles( $_SESSION ) ) {
            unset( $_SESSION[ self::SESSION_FILE_LIST ] );
        }
    }

    public function getToken() {
        $dao = new Users_UserDao();
        $user = $dao->getByUid( $this->session[ 'uid' ] );

        return self::getTokenByUser( $user );
    }

    /**
     * @param \Users_UserStruct $user
     * @return null|string
     *
     */
    public static function getTokenByUser( \Users_UserStruct $user ) {
        $serviceDao = new ConnectedServiceDao() ;
        $serviceStruct = $serviceDao->findDefaultServiceByUserAndName( $user, 'gdrive' ) ;

        if ( !$serviceStruct ) {
            return null ;
        }
        else {
            return $serviceStruct->getDecodedOauthAccessToken();
        }
    }


    public static function sessionHasFiles ( $session ) {
        if( isset( $session[ self::SESSION_FILE_LIST ] )
            && !empty( $session[ self::SESSION_FILE_LIST ] ) ) {
            return true;
        }

        return false;
    }

    public static function findFileIdByName ( $fileName, $session ) {
        if( self::sessionHasFiles( $session ) ) {
            $fileList = $session[ self::SESSION_FILE_LIST ];

            foreach ( $fileList as $fileId => $file ) {
                if( $file[ self::SESSION_FILE_NAME ] === $fileName ) {
                    return $fileId;
                }
            }
        }

        return null;
    }

    /**
     * Gets the service if token is available. If token is not found in database, then return null.
     *
     * Returned token may still be expired.
     *
     * @return \Google_Service_Drive|null
     */
    public function getService() {
        $token = $this->getToken() ;
        if ( $token ) {
            return RemoteFileService::getService( $token );
        } else {
            return null ;
        }
    }


}