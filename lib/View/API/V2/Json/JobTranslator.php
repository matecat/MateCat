<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.55
 *
 */

namespace View\API\V2\Json;


use Model\Translators\JobsTranslatorsStruct;
use Model\Users\UserDao;
use ReflectionException;

class JobTranslator
{


    protected JobsTranslatorsStruct $data;
    protected UserDao $userDao;

    public function __construct(JobsTranslatorsStruct $translatorsStruct, UserDao $userDao)
    {
        $this->data = $translatorsStruct;
        $this->userDao = $userDao;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ReflectionException
     * @throws \Exception
     */
    public function renderItem(JobsTranslatorsStruct $jTranslatorsStruct = null): array
    {
        if ($jTranslatorsStruct == null) {
            $jTranslatorsStruct = $this->data;
        }

        $translatorJson = [
            'email' => $jTranslatorsStruct->email,
            'added_by' => (int)$jTranslatorsStruct->added_by,
            'delivery_date' => $jTranslatorsStruct->delivery_date,
            'delivery_timestamp' => strtotime($jTranslatorsStruct->delivery_date),
            'source' => $jTranslatorsStruct->source,
            'target' => $jTranslatorsStruct->target,
            'id_translator_profile' => $jTranslatorsStruct->id_translator_profile,
            'user' => null
        ];

        if (!empty($jTranslatorsStruct->id_translator_profile)) {
            $user = $jTranslatorsStruct->getUser($this->userDao);
            if ($user !== null) {
                $translatorJson['user'] = User::renderItem($user);
            }
        }

        return $translatorJson;
    }

}