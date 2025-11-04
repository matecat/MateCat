<?php

namespace View\API\V2\Json;

class TranslationIssueComment
{

    private $data;

    /**
     * @return array
     */
    public function renderItem($record): array
    {
        return [
                'id'          => (int)$record->id,
                'uid'         => (int)$record->uid,
                'id_issue'    => (int)$record->id_qa_entry,
                'created_at'  => date('c', strtotime($record->create_date)),
                'message'     => $record->comment,
                'source_page' => $record->source_page
        ];
    }

    /**
     * @return array
     */
    public function render($array): array
    {
        $out = [];
        foreach ($array as $record) {
            $out[] = $this->renderItem($record);
        }

        return $out;
    }

}
