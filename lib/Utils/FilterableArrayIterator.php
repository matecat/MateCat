<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 11/08/17
 * Time: 15.05
 *
 */

class FilterableArrayIterator extends ArrayIterator {

    /**
     * @param $filter Closure|array
     *
     * @return FilterableArrayIterator
     */
    public function filter( $filter ){
        if( is_array( $filter ) ) return $this->_filterHasKeyValueEqualTo( $filter );
        else return $this->_filterCallback( $filter );
    }

    protected function _filterHasKeyValueEqualTo( Array $map = [] ){
        return $this->_filter( function ( $elem ) use ( $map ) {
            $take = true;
            foreach ( $map as $k => $v ) {
                if( ( isset( $elem[ $k ] ) && $elem[ $k ] == $v ) || ( property_exists( $elem, $k ) && $elem->$k == $v ) ){
                    $take &= true;
                } else {
                    $take &= false;
                }
            }
            return $take;
        } );
    }

    /**
     * Filters elements of an ArrayIterator using a callback function
     * If no callback is supplied, all entries of array equal to FALSE (see converting to boolean) will be removed.
     *
     * @param $callback
     *
     * @return FilterableArrayIterator
     */
    protected function _filterCallback( $callback = null ){
        if( !is_callable( $callback ) || empty( $callback ) ){
            return $this->_filter();
        }
        return $this->_filter( $callback );
    }

    protected function _filter( $callback = null ) {
        if ( empty( $callback ) ) return new self( array_filter( $this->getArrayCopy() ) );
        return new self( array_filter( $this->getArrayCopy(), $callback ) );
    }

    public function pop(){
        $lastRow = $this[ $this->count() -1 ];
        $this->offsetUnset( $this->count() -1 );
        return $lastRow;
    }

    public function shift(){
        $this->rewind();
        $firstRow = $this->current();
        $this->offsetUnset( 0 );
        return $firstRow;
    }

}