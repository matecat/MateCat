<?php

use Search\ReplaceEventStruct;

class Search_ReplaceEventDAO extends DataAccess_AbstractDao {

    const STRUCT_TYPE = ReplaceEventStruct::class;
    const TABLE       = 'replace_events';

    /**
     * @param $id_project
     * @param $password
     *
     * @return array
     */
    public static function getByProject( $id_project, $password ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT 
            * FROM replace_events r
            INNER JOIN jobs j ON r.id_job = j.id AND r.job_password = j.password
            INNER JOIN projects p on p.id=j.id_project
            WHERE p.id=:id_project AND p.password=:project_password
            ORDER BY created_at DESC
        ";

        $stmt = $conn->prepare( $query );
        $stmt->execute( [
                ':id_project'       => $id_project,
                ':project_password' => $password,
        ] );

        return @$stmt->fetchAll( \PDO::FETCH_CLASS, self::STRUCT_TYPE );
    }

    /**
     * @param array $options
     *
     * @return array
     */
    public static function search( $options = [] ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT * FROM " . self::TABLE . " WHERE 1 = 1 ";

        if ( isset( $options[ 'id_job' ] ) and '' !== $options[ 'id_job' ] ) {
            $query               .= " AND id_job = :id_job";
            $params[ ':id_job' ] = $options[ 'id_job' ];
        }

        if ( isset( $options[ 'job_password' ] ) and '' !== $options[ 'job_password' ] ) {
            $query                     .= " AND job_password = :job_password";
            $params[ ':job_password' ] = $options[ 'job_password' ];
        }

        if ( isset( $options[ 'bulk_version' ] ) and '' !== $options[ 'bulk_version' ] ) {
            $query                     .= " AND bulk_version = :bulk_version";
            $params[ ':bulk_version' ] = $options[ 'bulk_version' ];
        }

        if ( isset( $options[ 'type' ] ) and '' !== $options[ 'type' ] ) {
            $query             .= " AND type = :type";
            $params[ ':type' ] = $options[ 'type' ];
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $conn->prepare( $query );
        $stmt->execute( $params );

        return @$stmt->fetchAll( \PDO::FETCH_CLASS, self::STRUCT_TYPE );
    }

    /**
     * @param $id_segment
     *
     * @return array
     */
    public static function getBySegment( $id_segment ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT * FROM " . self::TABLE . " WHERE id_segment = :id_segment ORDER BY created_at DESC";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id_segment' => $id_segment
        ] );

        return @$stmt->fetchAll( \PDO::FETCH_CLASS, self::STRUCT_TYPE );
    }

    /**
     * @param $eventStructId
     *
     * @return mixed
     */
    public static function getById( $eventStructId ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT * FROM " . self::TABLE . " WHERE id = :id ";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id' => $eventStructId
        ] );

        return @$stmt->fetchObject( self::STRUCT_TYPE )[ 0 ];
    }

    /**
     * @return int
     */
    public static function getCurrentBulkVersion( $id_job ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT MAX(bulk_version) as version FROM " . self::TABLE . " WHERE id_job=:id_job ";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id_job' => (int)$id_job,
        ] );

        $results = $stmt->fetch();

        return ( isset( $results[ 'version' ] ) ) ? $results[ 'version' ] : 0;
    }

    /**
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public static function save( ReplaceEventStruct $eventStruct ) {
        $conn = Database::obtain()->getConnection();

        // if not directly passed
        // try to assign the current version of the segment if it exists
        if ( null === $eventStruct->segment_version ) {
            $eventStruct->segment_version = self::getSegmentVersionNumber( $eventStruct->id_segment );
        }

        // calculate segment words delta
        $segment_words_delta = strlen( $eventStruct->segment_before_replacement ) - strlen( $eventStruct->segment_after_replacement );

        // insert query
        $query = "INSERT INTO " . self::TABLE . "
        (id_job, bulk_version, job_password, id_segment, source, target, replacement, segment_version, segment_before_replacement, segment_after_replacement, segment_words_delta, created_at)
        VALUES
        (:id_job, :bulk_version, :job_password, :id_segment, :source, :target, :replacement, :segment_version, :segment_before_replacement, :segment_after_replacement, :segment_words_delta, :created_at)
        ";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id_job'                     => $eventStruct->id_job,
                ':bulk_version'               => $eventStruct->replace_version,
                ':job_password'               => $eventStruct->job_password,
                ':id_segment'                 => $eventStruct->id_segment,
                ':source'                     => $eventStruct->source,
                ':target'                     => $eventStruct->target,
                ':replacement'                => $eventStruct->replacement,
                ':segment_version'            => $eventStruct->segment_version,
                ':segment_before_replacement' => $eventStruct->segment_before_replacement,
                ':segment_after_replacement'  => $eventStruct->segment_after_replacement,
                ':segment_words_delta'        => $segment_words_delta,
                ':created_at'                 => date( 'Y-m-d H:i:s' ),
        ] );

        return $stmt->rowCount();
    }

    /**
     * @param $id_segment
     *
     * @return int|null
     */
    private static function getSegmentVersionNumber( $id_segment ) {
        $conn = Database::obtain()->getConnection();

        $query = "SELECT version_number as version FROM segment_translations WHERE id_segment=:id_segment";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id_segment' => (int)$id_segment,
        ] );

        $results = $stmt->fetch();

        return ( isset( $results[ 'version' ] ) ) ? (int)$results[ 'version' ] : null;
    }

    /**
     * This method can be used to restore segment translations from a bulk version delta
     * So you can do an undo or a redo for a particular job
     *
     * @param int    $id_job
     * @param string $password
     * @param int    $type
     *
     * @return int
     */
    public static function move( $id_job, $password, $type ) {

        $allowedTypes = [
                'undo',
                'redo',
        ];

        if ( false === in_array( $type, $allowedTypes ) ) {
            throw new \InvalidArgumentException( 'Provided type is not valid' );
        }

        $actual = Search_ReplaceEventCurrentVersionDAO::getByIdJob( $id_job );

        if ( $type === 'undo' ) {
            $versionToMove =  $actual - 1;

            if ( $versionToMove === 0 ) {
                return 0;
            }

        } elseif ( $type === 'redo' ) {
            $versionToMove =  $actual + 1;

            if ( $versionToMove > self::getCurrentBulkVersion( $id_job ) ) {
                return 0;
            }
        }

        $conn          = Database::obtain()->getConnection();
        $affected_rows = 0;

        $events = self::search( [
                'id_job'       => $id_job,
                'job_password' => $password,
                'bulk_version' => $versionToMove,
        ] );

        if ( count( $events ) > 0 ) {

            $conn->beginTransaction();

            /** @var ReplaceEventStruct $result */
            foreach ( $events as $result ) {
                try {
                    $query = "UPDATE segment_translations SET translation = :translation WHERE id_job=:id_job AND id_segment=:id_segment";
                    $stmt  = $conn->prepare( $query );

                    $params = [
                            ':id_job'      => $result->id_job,
                            ':id_segment'  => $result->id_segment,
                            ':translation' => $result->segment_after_replacement
                    ];

                    $stmt->execute( $params );

                    $affected_rows++;
                } catch ( \Exception $e ) {
                    $conn->rollBack();
                    $affected_rows = 0;
                }
            }

            Search_ReplaceEventCurrentVersionDAO::save( $id_job, $versionToMove );

            $conn->commit();
        }

        return $affected_rows;
    }
}
