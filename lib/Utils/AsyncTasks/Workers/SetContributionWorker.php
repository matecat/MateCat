<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/16
 * Time: 20.36
 *
 */

namespace AsyncTasks\Workers;

use TaskRunner\Commons\AbstractElement,
        TaskRunner\Commons\AbstractWorker,
        TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\ReQueueException;

class SetContributionWorker extends AbstractWorker {

    public function process( AbstractElement $queueElement ) {
        // TODO: Implement process() method.
        $this->_doLog( "test" );
        sleep(2);
        throw new ReQueueException( "Req" );
    }

    protected function _checkForReQueueEnd( QueueElement $queueElement ) {
        // TODO: Implement _checkForReQueueEnd() method.
    }

}