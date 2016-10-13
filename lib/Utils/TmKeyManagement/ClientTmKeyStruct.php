<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 08/10/14
 * Time: 15.52
 */

/**
 * This object is meant to wrap an internal structure to expose only some values to the client and obfuscate others
 *
 * Class TmKeyManagement_ClientTmKeyStruct
 */
class TmKeyManagement_ClientTmKeyStruct extends TmKeyManagement_TmKeyStruct {

    /**
     * Flag that tells wether the key is editable or not by the current user.
     * @var int 0 or 1.
     */
    public $edit = true;

    /**
     * This function obfuscates the key before to send it to the client.<br />
     * A key is obfuscated by replacing all the characters except the last 4 ones with "*" characters.<br /><br />
     *
     * @param $uid
     * @return TmKeyManagement_ClientTmKeyStruct
     *
     * <b>Example</b><br />
     * 1234abcd1a2b  -->  *******d1a2b
     */
    public function hideKey( $uid ){

        if( $uid != $this->uid_transl && $uid != $this->uid_rev ){
            $this->key  = $this->getCrypt();
            $this->edit = false;
        }

        return $this;

    }

    /**
     * Facade loader for the client results
     *
     * @param TmKeyManagement_TmKeyStruct $keyToClone
     *
     * @return $this
     */
    public function loadInUsers( TmKeyManagement_TmKeyStruct $keyToClone ){
        $_userStructs = [];
        foreach( $keyToClone->getInUsers() as $userStruct ){
            $_userStructs[] = new Users_ClientUserFacade( $userStruct );
        }
        $this->in_users = $_userStructs;

        return $this;
    }

    /**
     * Used to force the shared parameters if the TmKeyManagement_ClientTmKeyStruct wraps a TmKeyManagement_TmKeyStruct
     * which comes from job data ( json keys in the database does not knows about sharing )
     *
     * @param $shared
     */
    public function setShared( $shared ){
        $this->is_shared = $shared;
    }

}