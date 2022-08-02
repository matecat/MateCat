<?php

use DataAccess\ShapelessConcreteStruct;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 22/09/15
 * Time: 16.42
 */
class LanguageStats_LanguageStatsDAO extends DataAccess_AbstractDao {

    const TABLE = "language_stats";

    const STRUCT_TYPE = "LanguageStats_LanguageStatsStruct";


    public function getLastDate() {

        $con  = $this->database->getConnection();
        $stmt = $con->prepare( "select max( date ) as date from " . self::TABLE );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute();

        return @$stmt->fetch()[ 'date' ];

    }

    public function getLanguageStats( DateTime $filterDate = null ) {


        if ( !$filterDate ) {
            $con  = $this->database->getConnection();
            $stmt = $con->prepare( "SELECT MAX( date ) as date FROM " . self::TABLE );
            $this->setCacheTTL( 24 * 60 * 60 );
            $filterDate = $this->_fetchObject( $stmt, new ShapelessConcreteStruct(), [] )[ 0 ][ 'date' ];
        } else {
            $filterDate = $filterDate->format( 'Y-m-d H:i:s' );
        }

        $query = "
          SELECT source, target, date, total_post_editing_effort, job_count, total_word_count, fuzzy_band
                FROM " . self::TABLE . "
                WHERE date = :filterDate
                AND job_count > 50 
                ;
          ";

        $con  = $this->database->getConnection();
        $stmt = $con->prepare( $query );

        return $this->_fetchObject( $stmt, new LanguageStats_LanguageStatsStruct(), [
                'filterDate' => $filterDate
        ] );

    }

    public function getSnapshotDates() {

        $query = "
                SELECT distinct DATE_FORMAT( date,'%Y-%m-%d' ) AS date_format , date
                FROM " . self::TABLE;

        $con  = $this->database->getConnection();
        $stmt = $con->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute();

        return $stmt->fetchAll();

    }

    public function getGraphData( ShapelessConcreteStruct $filters ) {

        $query = "
                  SELECT 
                    source, 
                    target, 
                    fuzzy_band, 
                    total_post_editing_effort, 
                    DATE_FORMAT( date, '%Y-%m' ) as date
                  FROM " . self::TABLE . "
                  WHERE 
                    date BETWEEN ? AND ?
                  AND
                    source IN( " . str_repeat( '?,', count( $filters->sources ) - 1 ) . '?' . " )
                  AND 
                    target IN( " . str_repeat( '?,', count( $filters->targets ) - 1 ) . '?' . " )
                  AND 
                    fuzzy_band IN( " . str_repeat( '?,', count( $filters->fuzzy_band ) - 1 ) . '?' . " )
                  ORDER BY 5 ASC
                  ;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $query );

        $values = array_merge(
                [
                        $filters->date_start,
                        $filters->date_end
                ],
                $filters->sources,
                $filters->targets,
                $filters->fuzzy_band
        );

        /**
         * @var $resultSet ShapelessConcreteStruct[]
         */
        $resultSet = $this->_fetchObject( $stmt, new ShapelessConcreteStruct(), $values );

        return $resultSet;

    }

    /**
     * @param DataAccess_IDaoStruct $obj
     *
     * @return LanguageStats_LanguageStatsStruct|null The inserted object on success, null otherwise
     * @throws Exception
     */
    public function create( DataAccess_IDaoStruct $obj ) {

        /**
         * @var $obj LanguageStats_LanguageStatsStruct
         */
        $obj = $this->sanitize( $obj );

        $this->_validateNotNullFields( $obj );

        $query = "INSERT INTO " . self::TABLE .
                " (date, source, target, fuzzy_band, total_word_count, total_post_editing_effort, total_time_to_edit, job_count)
                VALUES ( ?, ?, ?, ?, ?, ?, ?, ? )
                ON DUPLICATE KEY UPDATE
                          total_post_editing_effort = values( total_post_editing_effort ),
                          total_time_to_edit = values( total_time_to_edit ),
                          job_count = values( job_count )";

        $bind_values = [
                $obj->date,
                $obj->source,
                $obj->target,
                $obj->fuzzy_band,
                $obj->total_word_count,
                $obj->total_post_editing_effort,
                $obj->total_time_to_edit,
                (int)$obj->job_count
        ];

        $stmt = $this->getDatabaseHandler()->getConnection()->prepare( $query );
        $stmt->execute( $bind_values );

        //return the inserted object on success, null otherwise
        if ( $stmt->rowCount() > 0 ) {
            return $obj;
        }

        return null;
    }

    /**
     * @param DataAccess_IDaoStruct $obj
     *
     * @return array
     * @throws Exception
     */
    public function read( DataAccess_IDaoStruct $obj ) {

        /**
         * @var $obj LanguageStats_LanguageStatsStruct
         */
        $obj = $this->sanitize( $obj );

        $bind_values = [];
        $condition   = [];
        $query       = "SELECT date,
                                    source,
                                    target,
                                    total_word_count,
                                    total_post_editing_effort,
                                    total_time_to_edit,
                                    job_count
                             FROM " . self::TABLE . " WHERE %s";

        if ( $obj->date !== null ) {
            $condition[]   = "date = ?";
            $bind_values[] = $obj->date;
        }

        if ( $obj->source !== null ) {
            $condition    [] = "source = ?";
            $bind_values[]   = $obj->source;
        }

        if ( $obj->target !== null ) {
            $condition         [] = "target = ?";
            $bind_values[]        = $obj->target;
        }

        if ( $obj->fuzzy_band !== null ) {
            $condition    [] = "fuzzy_band = ?";
            $bind_values[]   = $obj->target;
        }

        if ( count( $condition ) ) {
            $where_string = implode( " AND ", $condition );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( $bind_values );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );

        return $stmt->fetchAll();
    }

    /**
     * @param $obj_arr LanguageStats_LanguageStatsStruct[] An array of LanguageStats_LanguageStatsStruct objects
     *
     * @return array|null The input array on success, null otherwise
     * @throws Exception
     */
    public function createList( Array $obj_arr ) {

        $obj_arr = $this->sanitizeArray( $obj_arr );

        $query = "INSERT INTO " . self::TABLE .
                " (date, source, target, fuzzy_band, total_word_count, total_post_editing_effort, total_time_to_edit, job_count)
                VALUES %s
                ON DUPLICATE KEY UPDATE
                          total_post_editing_effort = values( total_post_editing_effort ),
                          total_time_to_edit = values( total_time_to_edit ),
                          job_count = values( job_count )";

        //chunk array using MAX_INSERT_NUMBER
        $objects    = array_chunk( $obj_arr, 20 );
        $values     = [];
        $tuple_list = [];

        $allInsertPerformed = true;
        //create an insert query for each chunk
        foreach ( $objects as $i => $chunk ) {
            foreach ( $chunk as $obj ) {

                $tuple_list[] = "( ?, ?, ?, ?, ?, ?, ?, ? )";

                //fill values array
                $values = array_merge( $values, [
                        $obj->date,
                        $obj->source,
                        $obj->target,
                        $obj->fuzzy_band,
                        $obj->total_word_count,
                        $obj->total_post_editing_effort,
                        $obj->total_time_to_edit,
                        (int)$obj->job_count
                ] );

            }

            $insert_query = sprintf( $query, implode( ", ", $tuple_list ) );

            $stmt = $this->database->getConnection()->prepare( $insert_query );
            $stmt->execute( $values );

            if ( $stmt->errorCode() > 0 ) {
                $allInsertPerformed = false;
                break;
            }

            $values = [];
            $tuple_list = [];
        }

        if ( $allInsertPerformed ) {
            return $obj_arr;
        }

        return null;
    }

    /**
     * See parent definition
     * @see DataAccess_AbstractDao::sanitize
     *
     * @param LanguageStats_LanguageStatsStruct $input
     *
     * @return DataAccess_IDaoStruct|LanguageStats_LanguageStatsStruct
     * @throws Exception
     */
    public function sanitize( DataAccess_IDaoStruct $input ) {
        return parent::_sanitizeInput( $input, self::STRUCT_TYPE );
    }

    /**
     * See parent definition.
     * @see DataAccess_AbstractDao::sanitizeArray
     *
     * @param array $input
     *
     * @return array
     * @throws Exception
     */
    public static function sanitizeArray( array $input ) {
        return parent::_sanitizeInputArray( $input, self::STRUCT_TYPE );
    }


    /**
     * See in DataAccess_AbstractDao::validateNotNullFields
     * @see DataAccess_AbstractDao::_validateNotNullFields
     *
     * @param DataAccess_IDaoStruct $obj
     *
     * @return void
     * @throws Exception
     */
    protected function _validateNotNullFields( DataAccess_IDaoStruct $obj ) {

        /**
         * @var $obj LanguageStats_LanguageStatsStruct
         */
        if ( is_null( $obj->total_post_editing_effort ) || empty( $obj->total_post_editing_effort ) ) {
            throw new Exception( "Total postediting effort cannot be null" );
        }

        if ( is_null( $obj->total_time_to_edit ) ) {
            throw new Exception( "Total time to edit cannot be null" );
        }


        if ( is_null( $obj->job_count ) ) {
            throw new Exception( "Job count cannot be null" );
        }

        if ( is_null( $obj->total_word_count ) ) {
            throw new Exception( "Total wordcount cannot be null" );
        }

        if ( is_null( $obj->fuzzy_band ) ) {
            throw new Exception( "Fuzzy band cannot be null" );
        }

    }

}