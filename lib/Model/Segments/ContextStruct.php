<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/09/18
 * Time: 11.11
 *
 */

namespace Segments;

use ArrayAccess;
use DataAccess\ArrayAccessTrait;
use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class ContextStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, ArrayAccess {

    use ArrayAccessTrait;

    public $id;
    public $id_project;
    public $id_segment;
    public $id_file;
    public $context_json;

    public function __construct( array $array_params = [], $decode = true ) {
        parent::__construct( $array_params );
        if( $decode ){
            $this->context_json = json_decode( $this->context_json );
        }
    }

}