<?php

/**
 * Created by PhpStorm.
 * User: lavoro
 * Date: 24/06/16
 * Time: 17:25
 */
class Jobs_JobStatsDao extends DataAccess_AbstractDao {

    const TABLE = "jobs_stats";

    const STRUCT_TYPE = 'Jobs_JobStatsStruct';

    /**
     * @param $source string
     * @return Jobs_JobStatsStruct[]|null
     */
    public function readBySource( $source ){
        $con = $this->con->getConnection();
        $stmt = $con->prepare("
                      SELECT
                        fuzzy_band,
                        js.source,
                        js.target,
                        sum( js.total_time_to_edit ) as total_time_to_edit,
                        sum( js.total_raw_wc ) as total_raw_wc,
                        sum( COALESCE ( js.avg_post_editing_effort, 0) ) / sum( coalesce( js.total_raw_wc, 1) ) as total_post_editing_effort,
                        count(*) as job_count
                      FROM jobs_stats js 
                      WHERE
                        js.completed = 1
                        and js.source = :source
                      GROUP BY fuzzy_band, target"
        );

        $stmt->bindParam(':source', $source);
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute();

        $result = null;
        if($stmt->errorCode() == 0) {
            $result = $stmt->fetchAll();

        }
        return $result;
    }

    /**
     * @param $fuzzy_band
     * @return Jobs_JobStatsStruct[]|null
     */
    public function readByFuzzyBand ( $fuzzy_band ){
        $con = $this->con->getConnection();
        $stmt = $con->prepare("
                      SELECT
                      fuzzy_band,
                        js.source,
                        js.target,
                        sum( js.total_time_to_edit ) as total_time_to_edit,
                        sum( js.total_raw_wc ) as total_words,
                        sum( COALESCE ( js.avg_post_editing_effort, 0) ) / sum( coalesce( js.total_raw_wc, 1) ) as total_post_editing_effort,
                        count(*) as job_count
                      FROM
                             jobs_stats js 
                        join jobs j on j.id = js.id_job 
                                    and j.password = js.password
                      WHERE
                        j.completed = 1
                        and js.fuzzy_band = ':fuzzy_band'
                      GROUP BY target"
        );

        $stmt->bindParam(':fuzzy_band', $fuzzy_band);
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute();

        $result = null;
        if($stmt->errorCode() == 0) {
            $result = $stmt->fetchAll();

        }
        return $result;
    }

    /**
     * @param $source
     * @param $fuzzy_band
     * @return Jobs_JobStatsStruct[]|null
     */
    public function readBySourceAndFuzzyBand( $source, $fuzzy_band ){
        $con = $this->con->getConnection();
        $stmt = $con->prepare("
                      SELECT
                      fuzzy_band,
                        js.source,
                        js.target,
                        sum( js.total_time_to_edit ) as total_time_to_edit,
                        sum( js.total_raw_wc ) as total_words,
                        sum( COALESCE ( js.avg_post_editing_effort, 0) ) / sum( coalesce( js.total_raw_wc, 1) ) as total_post_editing_effort,
                        count(*) as job_count
                      FROM
                             jobs_stats js 
                        join jobs j on j.id = js.id_job 
                                    and j.password = js.password
                      WHERE
                        j.completed = 1
                        and js.fuzzy_band = ':fuzzy_band'
                        and js.source = ':source'
                      GROUP BY target"
        );

        $stmt->bindParam(':fuzzy_band', $fuzzy_band);
        $stmt->bindParam(':source', $source);
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute();

        $result = null;
        if($stmt->errorCode() == 0) {
            $result = $stmt->fetchAll();

        }
        return $result;
    }

    /**
     * @param Jobs_JobStatsStruct $obj
     */
    public function create( Jobs_JobStatsStruct $obj ) {
        $query = "insert into jobs_stats 
                       (id_job, password, fuzzy_band, source, target, total_time_to_edit, avg_post_editing_effort, total_raw_wc)
                  VALUES (:id_job, :password, :fuzzy_band, :source, :target, :total_time_to_edit, :avg_post_editing_effort, :total_raw_wc)
                  on duplicate key update 
                       avg_post_editing_effort = VALUES (avg_post_editing_effort),
                       total_time_to_edit = VALUES (total_time_to_edit),
                       total_raw_wc       = VALUES (total_raw_wc)";

        $con = $this->con->getConnection();

        $stmt = $con->prepare( $query );

        $stmt->bindParam(':id_job', $obj->id_job );
        $stmt->bindParam(':password', $obj->password );
        $stmt->bindParam(':fuzzy_band', $obj->fuzzy_band );
        $stmt->bindParam(':source', $obj->source );
        $stmt->bindParam(':target', $obj->target );
        $stmt->bindParam(':total_time_to_edit', $obj->total_time_to_edit );
        $stmt->bindParam(':avg_post_editing_effort', $obj->avg_post_editing_effort );
        $stmt->bindParam(':total_raw_wc', $obj->total_raw_wc );

        $stmt->execute();

        if($stmt->rowCount()){
            return $obj;
        }
        return null;
    }

    /**
     * @deprecated
     */
    public function _buildResult( $array_result ) {
        // TODO: Implement _buildResult() method.
    }
}

