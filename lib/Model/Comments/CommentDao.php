<?php

use Comments\OpenThreadsStruct;
use DataAccess\ShapelessConcreteStruct;

class Comments_CommentDao extends DataAccess_AbstractDao {

    const TABLE       = "comments";
    const STRUCT_TYPE = "Comments_CommentStruct";

    protected static $auto_increment_field = [ 'id' ];
    protected static $primary_keys         = [ 'id' ];

    const TYPE_COMMENT = 1;
    const TYPE_RESOLVE = 2;
    const TYPE_MENTION = 3;

    const SOURCE_PAGE_REVISE    = 2;
    const SOURCE_PAGE_TRANSLATE = 2;


    /**
     * Returns a structure that lists open threads count
     *
     * @return  array(
     *        'id_project' => 1,
     *        'password' => 'xxxx',
     *        'id_job' => 2,
     *        'id_segment' => 3,
     *        'count' => 42
     * );
     *
     * @param $projectIds
     *
     */
    public function getOpenThreadsForProjects( $projectIds ) {

        $ids = implode(',', array_map(function( $id ) {
            return (int) $id ;
        }, $projectIds ) );


        $sql = "
        SELECT id_project, jobs.password, id_job, COUNT( DISTINCT id_segment ) AS count
            FROM projects
            JOIN jobs ON jobs.id_project = projects.id
            JOIN comments ON comments.id_job = jobs.id
              AND comments.id_segment >= jobs.job_first_segment
              AND comments.id_segment <= jobs.job_last_segment
              AND comments.resolve_date IS NULL

        WHERE projects.id IN ( $ids )

        GROUP BY id_project, id_job, jobs.password
 ";

        $con = $this->database->getConnection() ;
        $stmt = $con->prepare( $sql ) ;

        return $this->_fetchObject( $stmt, new OpenThreadsStruct(), [] );

    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function deleteComment($id)
    {
        $sql = "DELETE from comments WHERE id = :id";
        $con = $this->database->getConnection() ;
        $stmt = $con->prepare( $sql ) ;

        return $stmt->execute([
                'id' => $id
        ]);
    }

    /**
     * @param $idSegment
     *
     * @return bool|int
     */
    public function destroySegmentIdCache($idSegment)
    {
        $con = $this->database->getConnection() ;
        $stmt = $con->prepare( "SELECT * from comments WHERE id_segment = :id_segment and (message_type = :message_type_comment or message_type = :message_type_resolve) order by id asc" ) ;

        return $this->_destroyObjectCache( $stmt, [
            'id_segment' => $idSegment,
            'message_type_comment' => Comments_CommentDao::TYPE_COMMENT,
            'message_type_resolve' => Comments_CommentDao::TYPE_RESOLVE,
        ] );
    }

    /**
     * @param     $idSegment
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]
     */
    public function getBySegmentId($idSegment, $ttl = 7200)
    {
        $sql = "SELECT * from comments WHERE id_segment = :id_segment and (message_type = :message_type_comment or message_type = :message_type_resolve) order by id asc";
        $stmt = $this->_getStatementForCache($sql);

        return $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_segment' => $idSegment,
                'message_type_comment' => Comments_CommentDao::TYPE_COMMENT,
                'message_type_resolve' => Comments_CommentDao::TYPE_RESOLVE,
        ] );
    }

    /**
     * @param     $id
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct
     */
    public function getById($id, $ttl = 86400)
    {
        $stmt = $this->_getStatementForCache("SELECT * from comments WHERE id = :id");

        return @$this->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id' => $id
        ] )[0];
    }

    /**
     * @param Comments_CommentStruct $obj
     *
     * @return Comments_CommentStruct
     * @throws Exception
     */
    public function saveComment( Comments_CommentStruct $obj ) {

        if ( $obj->message_type == null ) {
            $obj->message_type = self::TYPE_COMMENT;
        }

        $obj->timestamp   = time();
        $obj->create_date = date( 'Y-m-d H:i:s', $obj->timestamp );

        $this->validateComment( $obj );

        $this->database->insert( "comments", [
                'id_job'       => $obj->id_job,
                'id_segment'   => $obj->id_segment,
                'create_date'  => $obj->create_date,
                'email'        => $obj->email,
                'full_name'    => $obj->full_name,
                'uid'          => $obj->uid,
                'source_page'  => $obj->source_page,
                'message_type' => $obj->message_type,
                'message'      => $obj->message
        ] );

        $id = $this->database->last_insert();
        $obj->id = (int)$id;

        return $obj;
    }

    public function resolveThread( Comments_CommentStruct $obj ) {

        $obj->message_type = self::TYPE_RESOLVE;
        $obj->resolve_date = date( 'Y-m-d H:i:s' );

        $this->database->begin();

        try {

            $comment = $this->saveComment( $obj );

            self::updateFields(
                    [ 'resolve_date' => $obj->resolve_date ],
                    [
                            'id_segment'   => $obj->id_segment,
                            'id_job'       => $obj->id_job,
                            'resolve_date' => null
                    ]
            );

            $this->database->commit();

        } catch ( Exception $e ) {
            $err = $e->getMessage();
            Log::doJsonLog( "Error: " . var_export( $err, true ) );
            $this->database->rollback();
        }

        $obj->thread_id   = $obj->getThreadId();
        $obj->create_date = $comment->create_date;
        $obj->timestamp   = $comment->timestamp;

        return $obj;
    }

    public function getThreadContributorUids( Comments_CommentStruct $obj ) {

        $bind_values = [
                'id_job' => $obj->id_job,
                'id_segment' => $obj->id_segment
        ];

        $query = "SELECT DISTINCT(uid) FROM " . self::TABLE .
                " WHERE id_job = :id_job 
                  AND id_segment = :id_segment 
                  AND uid IS NOT NULL ";

        if ( $obj->uid ) {
            $bind_values[ 'uid' ] = $obj->uid;
            $query .= " AND uid <> :uid ";
        }

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( $bind_values );
        return $stmt->fetchAll();

    }

    public function getThreadsBySegments( $segments_id, $job_id ) {

        $prepare_str_segments_id = str_repeat( 'UNION SELECT ? ', count( $segments_id ) - 1 );

        $db             = Database::obtain()->getConnection();
        $comments_query = "SELECT * FROM comments 
        JOIN ( 
                SELECT ? as id_segment
                " . $prepare_str_segments_id . "
        ) AS SLIST USING( id_segment )
        WHERE message_type IN (1,2) AND id_job = ? ";

        $stmt = $db->prepare( $comments_query );
        $stmt->setFetchMode( PDO::FETCH_CLASS, "\Comments_BaseCommentStruct" );
        $stmt->execute( array_merge($segments_id, array($job_id)) );

        return $stmt->fetchAll();
    }

    /**
     *
     * @param Chunks_ChunkStruct $chunk
     *
     * @return Comments_BaseCommentStruct[]
     */

    public static function getCommentsForChunk( Jobs_JobStruct $chunk, $options = array() ) {

        $sql = "SELECT 
                  id, 
                  uid, 
                  resolve_date, 
                  id_job, 
                  id_segment, 
                  create_date, 
                  full_name, 
                  source_page, 
                  message_type, 
                  message, 
                  email, 
                  IF ( resolve_date IS NULL, NULL, MD5( CONCAT( id_job, '-', id_segment, '-', resolve_date ) ) 
                ) AS thread_id 
                FROM " . self::TABLE . "
                WHERE id_job = :id_job 
                AND message_type IN(1,2)
                ORDER BY id_segment ASC, create_date ASC ";

        $params = [ 'id_job' => $chunk->id ];

        if ( array_key_exists( 'from_id', $options ) && $options['from_id'] != null ) {
            $sql = $sql . " AND id >= :from_id " ;
            $params['from_id'] = $options['from_id'] ;
        }

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $params );

        $stmt->setFetchMode( PDO::FETCH_CLASS, '\Comments_BaseCommentStruct' );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function validateComment( $obj ) {

        if ( ( $obj->message === null or $obj->message === '' )  and $obj->message_type == self::TYPE_COMMENT ) {
            throw new Exception( "Comment message can't be blank." );
        }

        if ( empty( $obj->full_name ) ) {
            throw new Exception( "Full name can't be blank." );
        }
    }

    protected function _buildResult( $array_result ) {
        $result = [];

        foreach ( $array_result as $item ) {

            $build_arr = [
                    'id'             => (int)$item[ 'id' ],
                    'id_job'         => $item[ 'id_job' ],
                    'id_segment'     => $item[ 'id_segment' ],
                    'create_date'    => $item[ 'create_date' ],
                    'full_name'      => $item[ 'full_name' ],
                    'thread_id'      => $item[ 'thread_id' ],
                    'email'          => $item[ 'email' ],
                    'message_type'   => $item[ 'message_type' ],
                    'message'        => $item[ 'message' ],
                    'formatted_date' => self::formattedDate( $item[ 'create_date' ] ),
                    'timestamp'      => (int)$item[ 'timestamp' ]
            ];

            $result[] = $build_arr;
        }

        return $result;
    }

    static function formattedDate( $time ) {
        return strftime( '%l:%M %p %e %b %Y UTC', strtotime( $time ) );
    }

    public static function placeholdContent($content){
        $users_ids = self::getUsersIdFromContent($content);
        $userDao = new Users_UserDao( Database::obtain() );
        $users = $userDao->getByUids( $users_ids );
        foreach($users as $user){
            $content = str_replace("{@".$user->uid."@}", "@".$user->first_name, $content);
        }

        $content = str_replace("{@team@}", "@team", $content);
        return $content;
    }

    public static function getUsersIdFromContent($content){

        $users = [];

        preg_match_all( "/\{\@([\d]+)\@\}/", $content, $find_users );
        if ( isset( $find_users[ 1 ] ) ) {
            $users = $find_users[1];
        }

        return $users;

    }

}
