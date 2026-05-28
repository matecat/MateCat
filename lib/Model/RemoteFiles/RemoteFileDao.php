<?php

namespace Model\RemoteFiles;

use Exception;
use Model\DataAccess\AbstractDao;
use PDO;
use PDOException;

class RemoteFileDao extends AbstractDao
{
    /**
     * @throws Exception
     */
    public function insert(int $id_file, int $id_job, string $remote_id, int $connected_service_id, int $is_original = 0): void
    {
        $data = [];
        $data['id_file'] = $id_file;
        $data['id_job'] = $id_job;
        $data['remote_id'] = $remote_id;
        $data['is_original'] = $is_original;
        $data['connected_service_id'] = $connected_service_id;

        $this->database->insert('remote_files', $data);
    }

    /**
     * @return RemoteFileStruct[]
     * @throws PDOException
     */
    public function getByJobId(int $id_job): array
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM remote_files " .
            " WHERE id_job = :id_job " .
            "   AND is_original = 0 "
        );

        $stmt->execute(['id_job' => $id_job]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, RemoteFileStruct::class);

        return $stmt->fetchAll();
    }

    /**
     * @return RemoteFileStruct[]
     * @throws PDOException
     */
    public function getOriginalsByJobId(int $id_job): array
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(
            "SELECT r.* FROM remote_files r " .
            " INNER JOIN files_job fj " .
            "    ON r.id_file = fj.id_file " .
            " WHERE fj.id_job = :id_job " .
            "   AND r.is_original = 1 " .
            " ORDER BY r.id_file "
        );

        $stmt->execute(['id_job' => $id_job]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, RemoteFileStruct::class);

        return $stmt->fetchAll();
    }

    /**
     * @return RemoteFileStruct[]
     * @throws PDOException
     */
    public function getByFileId(int $id_file, int $is_original = 0): array
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM remote_files " .
            " WHERE id_file = :id_file " .
            "   AND is_original = :is_original "
        );

        $stmt->execute(['id_file' => $id_file, 'is_original' => $is_original]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, RemoteFileStruct::class);

        return $stmt->fetchAll();
    }

    /**
     * @throws PDOException
     */
    public function getByFileAndJob(int $id_file, int $id_job): ?RemoteFileStruct
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM remote_files " .
            " WHERE id_file = :id_file " .
            "   AND id_job = :id_job" .
            "   AND is_original = 0 "
        );

        $stmt->execute(['id_file' => $id_file, 'id_job' => $id_job]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, RemoteFileStruct::class);

        return $stmt->fetch() ?: null;
    }

    /**
     * @throws PDOException
     */
    public function jobHasRemoteFiles(int $id_job): bool
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(
            "  SELECT count(id) "
            . "  FROM remote_files "
            . " WHERE id_job = :id_job "
            . "   AND is_original = 0 "
        );
        $stmt->setFetchMode(PDO::FETCH_NUM);
        $stmt->execute(['id_job' => $id_job]);

        $result = $stmt->fetch();

        $countRemoteFiles = $result[0];

        if ($countRemoteFiles > 0) {
            return true;
        }

        return false;
    }
}
