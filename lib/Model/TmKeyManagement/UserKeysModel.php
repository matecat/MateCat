<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/02/2018
 * Time: 14:50
 */

namespace Model\TmKeyManagement;

use Exception;
use Model\DataAccess\Database;
use Model\Users\UserStruct;
use Utils\Logger\LoggerFactory;
use Utils\TmKeyManagement\ClientTmKeyStruct;
use Utils\TmKeyManagement\Filter;

class UserKeysModel
{

    /** @var array{totals: array<int, ClientTmKeyStruct>, job_keys: list<ClientTmKeyStruct>} */
    protected array $_user_keys = ['totals' => [], 'job_keys' => []];

    protected UserStruct $user;

    protected string $userRole;

    public function __construct(UserStruct $user, string $role = Filter::ROLE_TRANSLATOR)
    {
        $this->user = $user;
        $this->userRole = $role;
    }

    /**
     * @param string $jobKeys
     * @param int $ttl
     *
     * @return array{totals: array<int, ClientTmKeyStruct>, job_keys: list<ClientTmKeyStruct>}
     * @throws Exception
     * @throws \TypeError
     */
    public function getKeys(string $jobKeys, int $ttl = 0): array
    {
        /*
         * Take the keys of the user
         */
        try {
            $_keyDao = new MemoryKeyDao(Database::obtain());
            $dh = new MemoryKeyStruct(['uid' => $this->user->uid]);
            $keyList = $_keyDao->read($dh, false, $ttl);
        } catch (Exception $e) {
            $keyList = [];
            LoggerFactory::doJsonLog($e->getMessage());
        }

        $reverse_lookup_user_personal_keys = ['pos' => [], 'elements' => []];

        /** @var MemoryKeyStruct[] $keyList */
        foreach ($keyList as $_j => $key) {
            $tmKey = $key->tm_key;
            if ($tmKey === null) {
                continue;
            }

            $reverse_lookup_user_personal_keys['pos'][$_j] = $tmKey->key;
            $reverse_lookup_user_personal_keys['elements'][$_j] = $key;

            $this->_user_keys['totals'][$_j] = new ClientTmKeyStruct($tmKey);
        }

        /*
         * Now take the JOB keys
         */
        $job_keyList = json_decode($jobKeys, true);

        /** @var array<int, array<string, mixed>> $job_keyList */
        foreach ($job_keyList as $jobKey) {
            $jobKey = new ClientTmKeyStruct($jobKey);
            $jobKey->complete_format = true;

            if (!is_null($this->user->uid) && count($reverse_lookup_user_personal_keys['pos'])) {
                /*
                 * If user has some personal keys, check for the job keys if they are present, and obfuscate
                 * when they are not
                 */
                $_index_position = array_search($jobKey->key, $reverse_lookup_user_personal_keys['pos']);
                if ($_index_position !== false) {
                    //I FOUND A KEY IN THE JOB THAT IS PRESENT IN MY KEYRING
                    //i'm owner?? and the key is an owner type key?
                    if (!$jobKey->owner && $this->userRole != Filter::OWNER) {
                        $jobKey->r = $jobKey->{Filter::$GRANTS_MAP[$this->userRole]['r']};
                        $jobKey->w = $jobKey->{Filter::$GRANTS_MAP[$this->userRole]['w']};
                        $jobKey = $jobKey->hideKey($this->user->uid);
                    } elseif ($jobKey->owner && $this->userRole != Filter::OWNER) {
                        // I'm not the job owner, but i know the key because it is in my keyring
                        // so, i can upload and download TMX, but i don't want it to be removed from job
                        // in tm.html relaxed the control to "key.edit" to enable buttons
                        // $jobKey = $jobKey->hideKey( $uid ); // enable editing

                    } elseif ($jobKey->owner && $this->userRole == Filter::OWNER) {
                        //do Nothing
                    }

                    //copy the is_shared value from the key inside the Keyring into the key coming from job
                    $jobKey->setShared($reverse_lookup_user_personal_keys['elements'][$_index_position]->tm_key?->isShared() ?? false);

                    unset($this->_user_keys['totals'][$_index_position]);
                } else {
                    /*
                     * This is not a key of that user, set right and obfuscate
                     */
                    $jobKey->r = true;
                    $jobKey->w = true;
                    $jobKey->owner = false;
                    $jobKey = $jobKey->hideKey(-1);
                }

                $this->_user_keys['job_keys'][] = $jobKey;
            } else {
                /*
                 * This user is anonymous or it has no keys in its keyring, obfuscate all
                 */
                $jobKey->r = true;
                $jobKey->w = true;
                $jobKey->owner = false;
                $this->_user_keys['job_keys'][] = $jobKey->hideKey(-1);
            }
        }

        //clean unordered keys
        $this->_user_keys['totals'] = array_values($this->_user_keys['totals']);

        return $this->_user_keys;
    }


}