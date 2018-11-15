<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 14.05
 *
 */

namespace SubFiltering\Commons;

class Pipeline {

    /**
     * @var AbstractHandler[]
     */
    protected $handlers;

    public function __construct() {

    }

    /**
     * @param AbstractHandler $handler
     *
     * @return Pipeline
     */
    public function addFirst( AbstractHandler $handler ) {
        $this->_attach( $handler );
        array_unshift( $this->handlers, $handler );

        return $this;
    }

    /**
     * @param AbstractHandler $newPipeline
     * @param AbstractHandler $before
     *
     * @return Pipeline
     */
    public function addBefore( AbstractHandler $newPipeline, AbstractHandler $before ) {
        $this->_attach( $newPipeline );
        foreach ( $this->handlers as $pos => $handler ) {
            if ( $handler->getName() == $before->getName() ) {
                array_splice( $this->handlers, $pos, 0, $newPipeline );
                break;
            }
        }

        return $this;

    }

    /**
     * @param AbstractHandler $newPipeline
     * @param AbstractHandler $before
     *
     * @return Pipeline
     */
    public function addAfter( AbstractHandler $newPipeline, AbstractHandler $before ) {
        $this->_attach( $newPipeline );
        foreach ( $this->handlers as $pos => $handler ) {
            if ( $handler->getName() == $before->getName() ) {
                array_splice( $this->handlers, $pos + 1, 0, $newPipeline );
                break;
            }
        }

        return $this;

    }

    /**
     * @param AbstractHandler $handler
     *
     * @return Pipeline
     */
    public function addLast( AbstractHandler $handler ) {
        $this->_attach( $handler );
        $this->handlers[] = $handler;

        return $this;
    }

    /**
     * @param $segment
     *
     * @return mixed
     */
    public function transform( $segment ) {

        foreach ( $this->handlers as $handler ) {
            $segment = $handler->transform( $segment );
        }

        return $segment;

    }

    /**
     * @param AbstractHandler $handler
     *
     * @return $this
     */
    protected function _attach( AbstractHandler $handler ) {
        $handler->setPipeline( $this );

        return $this;
    }

}