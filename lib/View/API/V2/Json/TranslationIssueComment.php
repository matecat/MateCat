<?php

namespace View\API\V2\Json;

use Model\LQA\EntryCommentStruct;

class TranslationIssueComment
{

    /**
     * @return array{id: int, uid: int, id_issue: int, created_at: string, message: string, source_page: int}
     */
    public function renderItem(EntryCommentStruct $record): array
    {
        return [
            'id' => (int)$record->id,
            'uid' => (int)$record->uid,
            'id_issue' => (int)$record->id_qa_entry,
            'created_at' => date('c', strtotime($record->create_date ?? '') ?: 0),
            'message' => $record->comment,
            'source_page' => $record->source_page
        ];
    }

    /**
     * @param list<EntryCommentStruct> $array
     * @return list<array{id: int, uid: int, id_issue: int, created_at: string, message: string, source_page: int}>
     */
    public function render(array $array): array
    {
        $out = [];
        foreach ($array as $record) {
            $out[] = $this->renderItem($record);
        }

        return $out;
    }

}
