<?php

namespace Model\Files;

use Exception;
use Model\DataAccess\AbstractDao;
use PDOException;
use ReflectionException;

class FileDao extends AbstractDao
{
    const string TABLE = "files";

    protected static array $auto_increment_field = ['id'];

    /**
     * @param array<int, int> $idFiles
     *
     * @return int
     * @throws PDOException
     */
    public function deleteFailedProjectFiles(array $idFiles = []): int
    {
        if (empty($idFiles)) {
            return 0;
        }

        $sql = "DELETE FROM files WHERE id IN ( " . str_repeat('?,', count($idFiles) - 1) . '?' . " ) ";
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($idFiles);

        return $stmt->rowCount();
    }

    /**
     * @return FileStruct[]
     * @throws PDOException
     * @throws Exception
     * @throws ReflectionException
     */
    public function getByJobId(int $id_job, int $ttl = 60): array
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM files " .
            " INNER JOIN files_job ON files_job.id_file = files.id " .
            " AND id_job = :id_job "
        );

        /** @var FileStruct[] */
        return $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, FileStruct::class, ['id_job' => $id_job]);
    }

    /**
     * @return FileStruct[]
     * @throws PDOException
     * @throws Exception
     * @throws ReflectionException
     */
    public function getByProjectId(int $id_project, int $ttl = 600): array
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare("SELECT * FROM files where id_project = :id_project ");

        /** @var FileStruct[] */
        return $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, FileStruct::class, ['id_project' => $id_project]);
    }

    /**
     * @throws PDOException
     */
    public function updateField(FileStruct $file, string $field, string|int|float|bool|null $value): bool
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(
            "UPDATE files SET $field = :value " .
            " WHERE id = :id "
        );

        return $stmt->execute([
            'value' => $value,
            'id' => $file->id
        ]);
    }

    /**
     * @throws PDOException
     */
    public function isFileInProject(int $id_file, int $id_project): int
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare("SELECT * FROM files where id_project = :id_project and id = :id_file ");
        $stmt->execute(['id_project' => $id_project, 'id_file' => $id_file]);

        return $stmt->rowCount();
    }

    /**
     * @throws PDOException
     * @throws Exception
     * @throws ReflectionException
     */
    public function getById(int $id, ?int $ttl = 0): ?FileStruct
    {
        /** @var ?FileStruct $res */
        $res = $this->fetchById($id, FileStruct::class, $ttl);

        return $res;
    }

    /**
     * @throws Exception
     */
    public function insertFilesJob(int $id_job, int $id_file): void
    {
        $data = [];
        $data['id_job'] = (int)$id_job;
        $data['id_file'] = (int)$id_file;

        $this->database->insert('files_job', $data);
    }
}
