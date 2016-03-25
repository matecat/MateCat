<?php

class Json_RevisionData_Segments {
    private $data ;

    public function __construct( $data ) {
        $this->data = $data;
    }

    public function render() {
        if ( count($this->data) > 0 ) {
            return $this->processData();
        } else {
            return array( 'segments' => array() );
        }
    }

    private function processData() {
        $result = array() ;
        $result['segments'] = array();
        $comment_data        = array() ;
        $previous_id_segment = null ;
        $record              = null ;
        $any_comment         = false;

        foreach( $this->data as $k => $v ) {
            $split_id = $v['tmp_split_id'];
            if ($split_id != null) {
                $id_segment = "{$v['id_segment']}-{$split_id}";
            } else {
                $id_segment = $v['id_segment'];
            }

            if ( $previous_id_segment != $id_segment ) {
                if ( $previous_id_segment ) {
                    $result['segments'][$last_k] = $record ;

                    if ( $any_comment ) {
                        $result['segments'][$last_k]['comments'] = $comment_data ;
                    }
                }

                $comment_data = array();
                $any_comment = false;
            }

            $record = array(
                'id'               => $id_segment,
                'source'           => $v['source'],
                'translatorTarget' => $v['translator_target'],
                'revisorTarget'    => $v['revisor_target'],
                'status'           => $v['status']
            );

            $this->processSplit( $record, $v );
            $this->attachIssues( $record, $v );

            $any_comment = $v['comment_date'] != null ;

            if ( $any_comment ) {
                $comment_data[] = array(
                    'created_at'  => $v['comment_date'],
                    'username'   => $v['username'],
                    'email'      => $v['email'],
                    'message'    => $v['comment_message'],
                    'resolvedAt' => $v['resolve_date']
                );
            }

            $last_k = $k;
            $previous_id_segment = $id_segment ;
        }

        $result['segments'][$last_k] = $record ;

        if ( $any_comment ) {
            $result['segments'][$last_k]['comments'] = $comment_data ;
        }

        if ( empty( $result[ 'segments' ] ) ) {
            return array() ;
        } else {
            return array('segments' => array_values($result['segments'])) ;
        }
    }

    private function processSplit(&$record, $v) {
        // TODO
    }

    private function attachIssues(&$record, $v) {
        $types = array(
            'typing', 'translation',
            'terminology', 'language',
            'style'
        );

        $issues = array();
        foreach( $types as $t ) {
            $issues[] = array( 'type' => $t, 'value' => $v["err_$t"] ) ;
        }

        $record['issues'] = $issues ;
    }

}
