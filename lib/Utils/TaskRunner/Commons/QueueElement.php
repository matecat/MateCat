<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 12.32
 *
 */

namespace TaskRunner\Commons;

/**
 * Class QueueElement
 * @package TaskRunner\Commons
 */
class QueueElement extends AbstractElement {

    /**
     * Worker class definition to be loaded by Executor
     * @var string
     */
    public $classLoad;

    /**
     * Data needed to execute the work
     *
     * @var Params
     */
    public $params;

    /**
     * Number of times the element is re-queued
     *
     * @var int
     */
    public $reQueueNum = 0;

    /**
     * Optional message for the re-queue
     *
     * @var string
     */
    public $reQueueMessage = '';

    /**
     * Magic method to serialize this object
     * @return string
     */
    public function __toString() {
        return json_encode($this);
    }

}