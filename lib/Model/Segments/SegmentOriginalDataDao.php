<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use ReflectionException;

class SegmentOriginalDataDao extends AbstractDao
{

    /**
     * @param int $id_segment
     * @param int $ttl
     *
     * @return SegmentOriginalDataStruct|null
     * @throws ReflectionException
     */
    public static function getBySegmentId(int $id_segment, int $ttl = 86400): ?SegmentOriginalDataStruct
    {
        $thisDao = new self();
        $conn    = $thisDao->getDatabaseHandler();
        $stmt    = $conn->getConnection()->prepare("SELECT * FROM segment_original_data WHERE id_segment = ? ");

        return $thisDao->setCacheTTL($ttl)->_fetchObjectMap(
                $stmt,
                SegmentOriginalDataStruct::class,
                [$id_segment]
        )[ 0 ] ?? null;
    }

    /**
     * @param int $id_segment
     * @param int $ttl
     *
     * @return array
     * @throws ReflectionException
     */
    public static function getSegmentDataRefMap(int $id_segment, int $ttl = 86400): array
    {
        $dataRefMap = self::getBySegmentId($id_segment, $ttl);

        if (empty($dataRefMap)) {
            return [];
        }

        $dataRefMapArray = $dataRefMap->getMap();

        return (!empty($dataRefMapArray)) ? $dataRefMapArray : [];
    }

    /**
     * @param int   $id_segment
     * @param array $map
     */
    public static function insertRecord(int $id_segment, array $map): void
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
                "INSERT INTO segment_original_data " .
                " ( id_segment, map  ) VALUES " .
                " ( :id_segment, :map ) "
        );

        // remove any carriage return or extra space from the map
        $json   = json_encode($map);
        $string = str_replace(["\\n", "\\r"], '', $json);
        $string = trim(preg_replace('/\s+/', ' ', $string));

        $stmt->execute([
                'id_segment' => $id_segment,
                'map'        => $string
        ]);
    }
}

