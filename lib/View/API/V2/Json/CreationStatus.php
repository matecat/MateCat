<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 20.06
 *
 */

namespace API\V2\Json;


use stdClass;

class CreationStatus {

    private $data;

    public function __construct( $data ) {
        $this->data = $data;
    }

    public function render() {
        $out = [];

        /**
         * @var $record stdClass
         */
        foreach ( $this->data as $record ) {

            $formatted = array(
                    'status'       => 'OK',
                    'message'      => 'Project created',
                    'id_project'   => $record->id_project,
                    'project_pass' => $record->ppassword,
                    'name'         => $record->name,
                    'new_keys'     => $record->new_keys,
                    'analyze_url'  => $record->analyze_url,
            );

            $out[] = $formatted;

        }

        return $out;
    }

}
