<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/04/17
 * Time: 17.18
 *
 */

namespace API\App\Json;


use Outsource\ConfirmationStruct;

class OutsourceConfirmation {

    protected $data;

    public function __construct( ConfirmationStruct $confirmation ) {
        $this->data = $confirmation;
    }

    public function render() {
        $result = $this->data->toArray();
        $class = get_class( $this->data );
        $result[ 'create_timestamp' ] = strtotime( $result[ 'create_date' ] );
        $result[ 'delivery_timestamp' ] = strtotime( $result[ 'delivery_date' ] );
        $result[ 'quote_review_link' ] = $class::REVIEW_ORDER_LINK . $this->data->quote_pid;
        unset( $result[ 'id' ] );
        return $result;
    }

}