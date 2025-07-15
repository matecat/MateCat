<?php

namespace Model\Comments;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use Model\Users\UserDao;
use PDO;
use ReflectionException;
use Utils\Logger\Log;

class CommentDao extends AbstractDao {

    const TABLE       = "comments";
    const STRUCT_TYPE = "CommentStruct";

    protected static array $auto_increment_field = [ 'id' ];
    protected static array $primary_keys         = [ 'id' ];

    const TYPE_COMMENT = 1;
    const TYPE_RESOLVE = 2;
    const TYPE_MENTION = 3;

    /**
     * Returns a structure that lists open threads count
     *
     * @param $projectIds
     *
     * @return  OpenThreadsStruct[];
     *
     * @throws ReflectionException
     */
    public function getOpenThreadsForProjects( $projectIds ): array {

        $ids = implode( ',', array_map( function ( $id ) {
            return (int)$id;
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

        $con  = $this->database->getConnection();
        $stmt = $con->prepare( $sql );

        return $this->_fetchObjectMap( $stmt, OpenThreadsStruct::class, [] );

    }

    /**
     * @param BaseCommentStruct $comment
     *
     * @return bool
     * @throws ReflectionException
     */
    public function deleteComment( BaseCommentStruct $comment ): bool {
        $sql  = "DELETE from comments WHERE id = :id";
        $con  = $this->database->getConnection();
        $stmt = $con->prepare( $sql );

        $this->destroySegmentIdSegmentCache( $comment->id_segment );

        return $stmt->execute( [
                'id' => $comment->id
        ] );
    }

    /**
     * @param int $idSegment
     *
     * @return bool
     * @throws ReflectionException
     */
    public function destroySegmentIdSegmentCache( int $idSegment ): bool {
        $con  = $this->database->getConnection();
        $stmt = $con->prepare( "SELECT * from comments WHERE id_segment = :id_segment and (message_type = :message_type_comment or message_type = :message_type_resolve) order by id" );

        return $this->_destroyObjectCache( $stmt,
                BaseCommentStruct::class,
                [
                        'id_segment'           => $idSegment,
                        'message_type_comment' => CommentDao::TYPE_COMMENT,
                        'message_type_resolve' => CommentDao::TYPE_RESOLVE,
                ] );
    }

    /**
     * @param int $idSegment
     * @param int $ttl
     *
     * @return BaseCommentStruct[]
     * @throws ReflectionException
     */
    public function getBySegmentId( int $idSegment, int $ttl = 7200 ): array {
        $sql  = "SELECT * from comments WHERE id_segment = :id_segment and (message_type = :message_type_comment or message_type = :message_type_resolve) order by id";
        $stmt = $this->_getStatementForQuery( $sql );

        return $this->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, BaseCommentStruct::class, [
                'id_segment'           => $idSegment,
                'message_type_comment' => CommentDao::TYPE_COMMENT,
                'message_type_resolve' => CommentDao::TYPE_RESOLVE,
        ] );
    }

    /**
     * @param     $id
     * @param int $ttl
     *
     * @return BaseCommentStruct|null
     * @throws ReflectionException
     */
    public function getById( $id, int $ttl = 86400 ): ?BaseCommentStruct {
        $stmt = $this->_getStatementForQuery( "SELECT * from comments WHERE id = :id" );

        /** @var $res BaseCommentStruct */
        $res = $this->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, BaseCommentStruct::class, [
                'id' => $id
        ] )[ 0 ] ?? null;

        return $res;
    }

    /**
     * @param CommentStruct $obj
     *
     * @return CommentStruct
     * @throws Exception
     */
    public function saveComment( CommentStruct $obj ): CommentStruct {

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
                'is_anonymous' => $obj->is_anonymous ?: 0,
                'message_type' => $obj->message_type,
                'message'      => $obj->message
        ] );

        $id      = $this->database->last_insert();
        $obj->id = (int)$id;

        $this->destroySegmentIdSegmentCache( $obj->id_segment );

        return $obj;
    }

    public function resolveThread( CommentStruct $obj ): CommentStruct {

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

            $obj->thread_id   = $obj->getThreadId();
            $obj->create_date = $comment->create_date;
            $obj->timestamp   = $comment->timestamp;

            $this->destroySegmentIdSegmentCache( $obj->id_segment );

        } catch ( Exception $e ) {
            $err = $e->getMessage();
            Log::doJsonLog( "Error: " . var_export( $err, true ) );
            $this->database->rollback();
        }

        return $obj;

    }

    public function getThreadContributorUids( CommentStruct $obj ): array {

        $bind_values = [
                'id_job'     => $obj->id_job,
                'id_segment' => $obj->id_segment
        ];

        $query = "SELECT DISTINCT(uid) FROM " . self::TABLE .
                " WHERE id_job = :id_job 
                  AND id_segment = :id_segment 
                  AND uid IS NOT NULL ";

        if ( $obj->uid ) {
            $bind_values[ 'uid' ] = $obj->uid;
            $query                .= " AND uid <> :uid ";
        }

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( $bind_values );

        return $stmt->fetchAll();

    }

    public function getThreadsBySegments( $segments_id, $job_id ): array {

        $prepare_str_segments_id = str_repeat( 'UNION SELECT ? ', count( $segments_id ) - 1 );

        $db             = Database::obtain()->getConnection();
        $comments_query = "SELECT * FROM comments 
        JOIN ( 
                SELECT ? as id_segment
                " . $prepare_str_segments_id . "
        ) AS SLIST USING( id_segment )
        WHERE message_type IN (1,2) AND id_job = ? ";

        $stmt = $db->prepare( $comments_query );
        $stmt->setFetchMode( PDO::FETCH_CLASS, BaseCommentStruct::class );
        $stmt->execute( array_merge( $segments_id, [ $job_id ] ) );

        return $stmt->fetchAll();
    }

    /**
     *
     * @param \Model\Jobs\JobStruct $chunk
     * @param array                 $options
     *
     * @return BaseCommentStruct[]
     */

    public static function getCommentsForChunk( JobStruct $chunk, array $options = [] ): array {

        $sql = "SELECT 
                  id, 
                  uid, 
                  resolve_date, 
                  id_job, 
                  id_segment, 
                  create_date, 
                  IF( is_anonymous = 0, full_name, 'Anonymous' ) as full_name, 
                  source_page, 
                  is_anonymous,
                  message_type, 
                  message, 
                  email, 
                  IF ( resolve_date IS NULL, NULL, MD5( CONCAT( id_job, '-', id_segment, '-', resolve_date ) ) 
                ) AS thread_id 
                FROM " . self::TABLE . "
                WHERE id_job = :id_job 
                AND message_type IN(1,2)
                ORDER BY id_segment, create_date";

        $params = [ 'id_job' => $chunk->id ];

        if ( array_key_exists( 'from_id', $options ) && $options[ 'from_id' ] != null ) {
            $sql                 = $sql . " AND id >= :from_id ";
            $params[ 'from_id' ] = $options[ 'from_id' ];
        }

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $params );

        $stmt->setFetchMode( PDO::FETCH_CLASS, BaseCommentStruct::class );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @throws Exception
     */
    private function validateComment( $obj ) {

        if ( ( $obj->message === null or $obj->message === '' ) and $obj->message_type == self::TYPE_COMMENT ) {
            throw new Exception( "Comment message can't be blank." );
        }

        if ( empty( $obj->full_name ) ) {
            throw new Exception( "Full name can't be blank." );
        }
    }

    protected function _buildResult( array $array_result ): array {
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

    /**
     * @throws ReflectionException
     */
    public static function placeholdContent( $content ) {
        $users_ids = self::getUsersIdFromContent( $content );
        $userDao   = new UserDao( Database::obtain() );
        $users     = $userDao->getByUids( $users_ids );
        foreach ( $users as $user ) {
            $content = str_replace( "{@" . $user->uid . "@}", "@" . $user->first_name, $content );
        }

        return str_replace( "{@team@}", "@team", $content );
    }

    public static function getUsersIdFromContent( $content ): array {

        $users = [];

        preg_match_all( "/\{@(\d+)@}/", $content, $find_users );
        if ( isset( $find_users[ 1 ] ) ) {
            $users = $find_users[ 1 ];
        }

        return $users;

    }

}
