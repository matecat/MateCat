<?php

class Comments_BaseCommentStruct extends \DataAccess\AbstractDaoSilentStruct implements \DataAccess\IDaoStruct, JsonSerializable {

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
                'full_name'    => $this->getFullName(),
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

    /**
     * @param bool $article
     * @return string
     */
    public function getFullName($article = false)
    {
        if($this->is_anonymous == true){

            $source_page = (int)$this->source_page;

            switch ($source_page){
                default:
                case 1:
                    return ($article == true) ? "the translator" : "Translator";

                case 2:
                    return ($article == true) ? "the revisor" : "Revisor";

                case 3:
                    return ($article == true) ? "the 2nd pass revisor" : "2nd pass revisor";
            }
        }

        return $this->full_name;
    }

}