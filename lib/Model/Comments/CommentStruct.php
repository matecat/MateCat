<?php

namespace Model\Comments;

use JsonSerializable;
use Model\DataAccess\IDaoStruct;

/**
 * This is NOT a database Entity, this is a utility vector to transport info.
 */
class CommentStruct extends BaseCommentStruct implements IDaoStruct, JsonSerializable {

    // database fields
    public int     $id;
    public int     $id_job;
    public int     $id_segment;
    public string  $create_date;
    public ?string $email        = null;
    public string  $full_name;
    public ?int    $uid          = null;
    public ?string $resolve_date = null;
    public int     $source_page;
    public ?int    $is_anonymous = 0;
    public ?int    $message_type = null;
    public ?string $message      = "";
    public int     $timestamp;
    public int     $revision_number;

    // returned values
    public ?string $thread_id = null;

    public static function getStruct(): CommentStruct {
        return new CommentStruct();
    }

    public function getFormattedDate(): string {
        return date_format( date_create( $this->create_date ), DATE_ATOM ) ?: date_format( date_create(), DATE_ATOM );
    }

    public function getThreadId(): string {
        return md5( $this->id_job . '-' . $this->id_segment . '-' . $this->resolve_date );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array {
        return [
                'id'           => $this->id,
                'uid'          => $this->uid,
                'id_job'       => $this->id_job,
                'id_segment'   => $this->id_segment,
                'is_anonymous' => $this->is_anonymous,
                'full_name'    => $this->getFullName(),
                'source_page'  => $this->source_page,
                'thread_id'    => $this->thread_id,
                'message'      => $this->message,
                'message_type' => $this->message_type,
                'create_at'    => $this->create_date,
                'resolved_at'  => $this->resolve_date,
                'timestamp'    => $this->timestamp,
        ];
    }
}
