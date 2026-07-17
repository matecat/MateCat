<?php

namespace Model\Files;

use Exception;
use Model\DataAccess\AbstractDao;
use PDOException;
use ReflectionException;

class FilesJobDao extends AbstractDao
{

    const string TABLE = 'files_job';

    /**
     * Verifies that a file part belongs to a file assigned to the given job (chunk).
     *
     * Used as an ownership gate against cross-job / cross-tenant access via a guessed
     * `file_part_id` (a globally unique auto-increment).
     *
     * @param int $filePartId
     * @param int $id_job
     * @param int $ttl
     *
     * @return bool
     * @throws PDOException
     * @throws Exception
     * @throws ReflectionException
     */
    public function isFilePartInJob(int $filePartId, int $id_job, int $ttl = 86400): bool
    {
        $conn = $this->database->getConnection();
        $sql  = "SELECT fj.id_job, fj.id_file
                FROM files_job fj
                JOIN files_parts fp ON fp.id_file = fj.id_file
                WHERE fp.id = :file_part_id
                AND fj.id_job = :id_job
                LIMIT 1";

        $stmt = $conn->prepare($sql);

        $result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, FilesJobStruct::class, [
            'file_part_id' => $filePartId,
            'id_job'       => $id_job,
        ]);

        return !empty($result);
    }
}
