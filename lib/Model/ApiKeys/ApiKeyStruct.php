<?php

namespace Model\ApiKeys;

use Exception;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PDOException;
use ReflectionException;

class ApiKeyStruct extends AbstractDaoObjectStruct implements IDaoStruct
{

    public ?int $id = null;
    public int $uid;
    public string $api_key;
    public string $api_secret;
    public string $create_date;
    public string $last_update;
    public bool $enabled;

    public function validSecret(string $secret): bool
    {
        return $this->api_secret == $secret;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws PDOException
     */
    public function getUser(UserDao $userDao): ?UserStruct
    {
        $userDao->setCacheTTL(3600);

        return $userDao->getByUid($this->uid);
    }
}
