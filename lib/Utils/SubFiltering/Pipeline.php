<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 14.05
 *
 */

namespace SubFiltering;

class Pipeline {

    /**
     * @var AbstractChannelHandler[]
     */
    protected $handlers;

    public function __construct() {

    }

    /**
     * @param AbstractChannelHandler $handler
     *
     * @return Pipeline
     */
    public function addFirst( AbstractChannelHandler $handler ) {
        $this->_attach( $handler );
        array_unshift( $this->handlers, $handler );

        return $this;
    }

    /**
     * @param AbstractChannelHandler $newPipeline
     * @param AbstractChannelHandler $before
     *
     * @return Pipeline
     */
    public function addBefore( AbstractChannelHandler $newPipeline, AbstractChannelHandler $before ) {
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
     * @param AbstractChannelHandler $newPipeline
     * @param AbstractChannelHandler $before
     *
     * @return Pipeline
     */
    public function addAfter( AbstractChannelHandler $newPipeline, AbstractChannelHandler $before ) {
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
     * @param AbstractChannelHandler $handler
     *
     * @return Pipeline
     */
    public function addLast( AbstractChannelHandler $handler ) {
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
     * @param AbstractChannelHandler $handler
     *
     * @return $this
     */
    protected function _attach( AbstractChannelHandler $handler ) {
        $handler->setPipeline( $this );

        return $this;
    }

}