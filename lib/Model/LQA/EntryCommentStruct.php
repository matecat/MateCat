<?php

namespace Model\LQA;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class EntryCommentStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public ?int $id = null;
    public int $uid;
    public int $id_qa_entry;
    public ?string $create_date = null;
    public string $comment;
    public int $source_page;

    /**
     * @param EntryCommentDao $dao
     * @param int $id
     * @param ?int $ttl
     *
     * @return EntryCommentStruct[]
     */
    public function getEntriesById(EntryCommentDao $dao, int $id, ?int $ttl = 86400): mixed
    {
        return $this->memoize(__METHOD__, function () use ($dao, $id) {
            return $dao->findByIssueId($id);
        });
    }
}
