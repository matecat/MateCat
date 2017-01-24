<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/01/17
 * Time: 16.41
 *
 */

namespace API\V2\Json;


use Projects_ProjectStruct;

class Project {

    public function __construct( Projects_ProjectStruct $data ) {
        $this->data = $data;
    }

    public function render() {
        unset( $this->data->id_engine_mt );
        unset( $this->data->id_engine_tm );
        unset( $this->data->for_debug );
        unset( $this->data->pretranslate_100 );
        return $this->data;
    }

}