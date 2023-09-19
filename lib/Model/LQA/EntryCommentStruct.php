<?php

namespace LQA;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class EntryCommentStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id;
    public $uid ;
    public $id_qa_entry ;
    public $create_date ;
    public $comment ;
    public $source_page ;

    /**
     * @param int $id
     * @param int $ttl
     *
     * @return mixed
     */
    public function getEntriesById( $id, $ttl = 86400 ) {
        return $this->cachable( __METHOD__, $this, function () use ( $id, $ttl ){
            return ( new EntryCommentDao() )->findByIssueId( $id );
        } );
    }
}
