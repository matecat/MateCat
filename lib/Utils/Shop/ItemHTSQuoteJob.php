<?php
/**
 * Created by PhpStorm.
 */

/**
 * Concrete Item for Shop_Cart Class
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 15/04/14
 * Time: 15.17
 *
 */
class Shop_ItemHTSQuoteJob extends Shop_AbstractItem {

    /**
     * These items will be the only accepted in setOffset/unsetOffset methods and ArrayAccess
     *
     * @see Shop_AbstractItem::$__storage
     * @see Shop_AbstractItem::offsetSet
     * @see Shop_AbstractItem::offsetUnset
     *
     * @var array
     */
    protected $__storage = array(
            'id'                  => null,
            'quantity'            => 1,
            'project_name'        => null,
            'name'                => null,
            'source'              => null,
            'target'              => null,
            'words'               => 0,
            'subject'             => 'general',
            'subject_printable'   => null,
            'currency'            => 'EUR',
            'timezone'            => '0',
            'quote_result'        => null,
            'outsourced'          => null,
            'quote_available'     => null,
            'typeOfService'       => "",
            'price'               => 0,
            'delivery'            => null,
            'r_price'             => 0,
            'r_delivery'          => null,
            'quote_pid'           => null,
            'show_info'           => null,
            'show_translator_data'=> null,
            'price_currency'      => 0,
            't_name'              => null,
            't_native_lang'       => null,
            't_words_specific'    => null,
            't_words_total'       => null,
            't_vote'              => null,
            't_positive_feedbacks'=> null,
            't_total_feedbacks'   => null,
            't_experience_years'  => null,
            't_education'         => null,
            't_chosen_subject'    => null,
            't_subjects'          => null,
            'show_revisor_data'   => null,
            'r_vote'              => null,
            'link_to_status'      => null
    );

    /**
     *
     * Because of compatibility with php 5.2 we can't use late static bindings in the abstract class ( introduced in php 5.3 )
     *
     * So we can't use 'new static' reserved word, we have to use 'new self'
     *
     * Workaround: declare this method into an interface, don't implement it in the abstract class
     * and declare real method every time in the same manner into the children
     *
     * @param $storage
     *
     * @return mixed
     *
     * @throws LogicException/DomainException
     */
    public static function getInflate( $storage ){
        $obj = new self();
        foreach( $storage as $key => $value ){
            $obj->offsetSet( $key, $value );
        }
        return $obj;
    }

}