<?php

/**
 * This is NOT a database Entity, this is a utility vector to transport info.
 */
class Comments_CommentStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    // database fields
    public int     $id;
    public int     $id_job;
    public int     $id_segment;
    public string  $create_date;
    public string  $email;
    public string  $full_name;
    public ?int    $uid          = null;
    public ?string $resolve_date = null;
    public int     $source_page;
    public ?int     $is_anonymous = 0;
    public ?int    $message_type = null;
    public ?string $message      = "";
    public int     $timestamp;
    public int     $revision_number;

    // returned values
    public ?string $thread_id = null;

    public static function getStruct(): Comments_CommentStruct {
        return new Comments_CommentStruct();
    }

    public function getFormattedDate(): string {
        return date_format( date_create( $this->create_date ), DATE_ATOM ) ?: date_format( date_create(), DATE_ATOM );
    }

    public function getThreadId(): string {
        return md5( $this->id_job . '-' . $this->id_segment . '-' . $this->resolve_date );
    }

}
