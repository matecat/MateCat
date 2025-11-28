<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 08/10/14
 * Time: 15.52
 */

namespace Utils\TmKeyManagement;

/**
 * This object is meant to wrap an internal structure to expose only some values to the client and obfuscate others
 *
 * Class ClientTmKeyStruct
 */
class ClientTmKeyStruct extends TmKeyStruct
{

    /**
     * Flag that tells wether the key is editable or not by the current user.
     * @var bool 0 or 1.
     */
    public bool $edit = true;

    /**
     * This function obfuscates the key before to send it to the client.<br />
     * A key is obfuscated by replacing all the characters except the last 4 ones with "*" characters.<br /><br />
     *
     * @param int $uid
     *
     * @return ClientTmKeyStruct
     *
     * <b>Example</b><br />
     * 1234abcd1a2b  -->  *******d1a2b
     */
    public function hideKey(int $uid): ClientTmKeyStruct
    {
        if ($uid != $this->uid_transl && $uid != $this->uid_rev) {
            $this->key  = $this->getCrypt();
            $this->edit = false;
        }

        return $this;
    }

    /**
     * Used to force the shared parameters if the ClientTmKeyStruct wraps a TmKeyStruct
     * which comes from job data ( json keys in the database does not knows about sharing )
     *
     * @param bool $shared
     */
    public function setShared(bool $shared): void
    {
        $this->is_shared = $shared;
    }

}