<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/07/2017
 * Time: 15:19
 */

namespace Features\Dqf\Model;

use Chunks_ChunkStruct;
use DataAccess_AbstractDao;
use Database;
use PDO;

class ChildProjectsMapDao extends DataAccess_AbstractDao  {

    const TABLE       = "dqf_child_projects_map";
    const STRUCT_TYPE = "\Features\Dqf\Model\ChildProjectsMapStruct";

    protected static $auto_increment_fields = [ 'id' ];
    protected static $primary_keys          = [ 'id' ];

    public function getByChunk( Chunks_ChunkStruct $chunk ) {
        $sql = "SELECT * FROM " . self::TABLE . " WHERE id_job = :id_job AND  " .
        " first_segment = :first_segment AND last_segment = :last_segment AND " .
        " archvied_at IS NULL ";

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute([
                'id_job'        => $chunk->id,
                'first_segment' => $chunk->job_first_segment,
                'last_segment'  => $chunk->job_last_segment
        ]);


    }
}