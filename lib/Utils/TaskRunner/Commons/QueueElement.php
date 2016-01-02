<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 12.32
 *
 */

namespace TaskRunner\Commons;

class QueueElement extends AbstractElement {

    /**
     * @var string
     */
    public $classLoad;

    /**
     * @var Params
     */
    public $params;

    /**
     * @var int
     */
    public $reQueueNum = 0;

    /**
     * @var string
     */
    public $reQueueMessage = '';

    /**
     * @return string
     */
    public function __toString() {
        return json_encode($this);
    }

}