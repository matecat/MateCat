<?php

namespace Analysis\Queue;
use TaskRunner\Commons\Context;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/12/15
 * Time: 16.59
 *
 */
class QueueInfo extends Context {

    /**
     * The key of the project list
     * ( needed to know the decremental counter for the number of elements. Will be read from the web page )
     * @var string
     */
    public $redis_key;

    /**
     * Number of elements in the queue on AMQ
     * @var int
     */
    public $queue_length = 0;

}
