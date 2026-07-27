<?php

namespace Model\Search;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDatabase;
use PDOException;

class MySQLReplaceEventIndexDao extends AbstractDao implements ReplaceEventIndexDaoInterface
{

    const string STRUCT_TYPE = ReplaceEventCurrentVersionStruct::class;
    const string TABLE = 'replace_events_current_version';

    private ?\PDO $pdo;

    public function __construct(IDatabase $con, ?\PDO $pdo = null)
    {
        parent::__construct($con);
        $this->pdo = $pdo;
    }

    /**
     * @param int $idJob
     *
     * @return int
     * @throws PDOException
     */
    public function getActualIndex(int $idJob): int
    {
        $conn = $this->pdo ?? $this->database->getConnection();
        $query = "SELECT version as v FROM " . self::TABLE . " WHERE id_job=:id_job";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':id_job' => $idJob,
        ]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? (int)($row['v'] ?? 0) : 0;
    }

    /**
     * @param int $id_job
     * @param int $version
     *
     * @return int
     * @throws PDOException
     */
    public function save(int $id_job, int $version): int
    {
        $conn = $this->pdo ?? $this->database->getConnection();

        if (0 !== $this->getActualIndex($id_job)) {
            $query = "UPDATE " . self::TABLE . " SET version = :version WHERE id_job=:id_job";
        } else {
            $query = "INSERT INTO " . self::TABLE . " (`version`, `id_job`) VALUES (:version, :id_job)";
        }

        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':id_job' => $id_job,
            ':version' => $version,
        ]);

        return $stmt->rowCount();
    }

    public function setTtl(int $ttl): void
    {
        // TODO: Implement setTtl() method.
    }
}
