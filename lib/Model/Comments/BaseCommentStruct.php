<?php

class Comments_BaseCommentStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, JsonSerializable {

    public int     $id;
    public int     $id_job;
    public int     $id_segment;
    public string  $create_date;
    public ?string $email        = null;
    public string  $full_name;
    public ?int    $uid          = null;
    public ?string $resolve_date = null;
    public int     $source_page;
    public ?int     $is_anonymous = 0;
    public int     $message_type;
    public ?string $message      = "";

    public function getThreadId(): ?string {
        return $this->resolve_date ? md5( $this->id_job . '-' . $this->id_segment . '-' . $this->resolve_date ) : null;
    }

    /**
     * @throws ReflectionException
     */
    public function templateMessage() {
        $this->message = Comments_CommentDao::placeholdContent( $this->message );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array {
        return [
                'id'           => $this->id,
                'id_job'       => $this->id_job,
                'id_segment'   => $this->id_segment,
                'create_at'    => date_format( date_create( $this->create_date ?: 'now' ), DATE_ATOM ),
                'full_name'    => !$this->is_anonymous ? $this->full_name : 'Anonymous',
                'uid'          => $this->uid,
                'resolved_at'  => !empty( $this->resolve_date ) ? date_format( date_create( $this->resolve_date ), DATE_ATOM ) : null,
                'is_anonymous' => $this->is_anonymous,
                'source_page'  => $this->source_page,
                'message_type' => $this->message_type,
                'message'      => $this->message,
                'thread_id'    => $this->getThreadId(),
                'timestamp'    => strtotime( $this->create_date ?: 'now' ),
        ];
    }

    public function toCommentStruct(): Comments_CommentStruct {
        return new Comments_CommentStruct( $this->toArray() );
    }

}