<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 17.36
 *
 */

namespace Analysis\Commons;

abstract class AbstractWorker {

    const ERR_REQUEUE_END      = 1;
    const ERR_REQUEUE          = 2;

    /**
     * Execution method
     *
     * @param $queueElement AbstractElement
     * @param $queueContext AbstractContext
     * @return mixed
     */
    abstract public function process( AbstractElement $queueElement, AbstractContext $queueContext );

}