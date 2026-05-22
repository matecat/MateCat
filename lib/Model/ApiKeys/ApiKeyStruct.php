<?php

namespace Model\ApiKeys;

use Exception;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\Database;
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
    private UserDao $userDao;

    public function __construct(array $array_params = [], ?UserDao $userDao = null)
    {
        parent::__construct($array_params);
        $this->userDao = $userDao ?? new UserDao(Database::obtain());
    }

    public function validSecret(string $secret): bool
    {
        return $this->api_secret == $secret;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws PDOException
     */
    public function getUser(): ?UserStruct
    {
        $this->userDao->setCacheTTL(3600);

        return $this->userDao->getByUid($this->uid);
    }
}
