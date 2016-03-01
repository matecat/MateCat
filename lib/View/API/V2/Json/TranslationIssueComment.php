<?php

namespace API\V2\Json  ;

class TranslationIssueComment {

    private $data ;

    /**
     * @return array
     */
    public function renderItem( $record ) {
        return array(
            'id'         => $record->id,
            'uid'        => $record->uid,
            'id_issue'   => $record->id_qa_entry,
            'created_at' => date('c', strtotime( $record->create_date) ),
            'message'    => $record->comment,
            'source_page' => $record->source_page
        );
    }

    /**
     * @return array
     */
    public function renderArray( $array ) {
        $out = array();
        foreach($array as $record) {
            $out[] = $this->renderItem( $record );
        }
        return $out;
    }

}
