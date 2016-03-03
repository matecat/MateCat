<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 17.36
 *
 */

namespace TaskRunner\Commons;
use \SplObserver;
use \SplSubject;

/**
 * Class AbstractWorker
 * @package TaskRunner\Commons
 */
abstract class AbstractWorker implements SplSubject {

    const ERR_REQUEUE_END      = 1;
    const ERR_REQUEUE          = 2;
    const ERR_EMPTY_ELEMENT    = 3;

    /**
     * Observers container
     *
     * @var SplObserver[]
     */
    protected $_observer;

    /**
     * The last log message
     *
     * @var string
     */
    protected $_logMsg;

    /**
     * This process ID
     *
     * @var int
     */
    protected $_workerPid = 0;

    /**
     * Set the caller pid. Needed to log the process Id.
     *
     * @param $_myPid
     */
    public function setPid( $_myPid ){
        $this->_workerPid = $_myPid;
    }

    /**
     * Execution method
     *
     * @param $queueElement AbstractElement
     * @param $queueContext Context
     *
     * @return mixed
     */
    abstract public function process( AbstractElement $queueElement, Context $queueContext );

    /**
     * Check how much times the element was re-queued and raise an Exception when the limit is reached ( 100 times )
     *
     * @param QueueElement $queueElement
     *
     * @return void
     */
    abstract protected function _checkForReQueueEnd(  QueueElement $queueElement );

    /**
     * Attach an SplObserver
     * @link  http://php.net/manual/en/splsubject.attach.php
     *
     * @param SplObserver $observer <p>
     *                              The <b>SplObserver</b> to attach.
     *                              </p>
     *
     * @return void
     * @since 5.1.0
     */
    public function attach( SplObserver $observer ) {
        $this->_observer[ spl_object_hash( $observer ) ] = $observer;
    }

    /**
     * Detach an observer
     * @link  http://php.net/manual/en/splsubject.detach.php
     *
     * @param SplObserver $observer <p>
     *                              The <b>SplObserver</b> to detach.
     *                              </p>
     *
     * @return void
     * @since 5.1.0
     */
    public function detach( SplObserver $observer ) {
        unset( $this->_observer[ spl_object_hash( $observer ) ] );
    }

    /**
     * Notify an observer
     * @link  http://php.net/manual/en/splsubject.notify.php
     * @return void
     * @since 5.1.0
     */
    public function notify() {
        foreach( $this->_observer as $hash => $observer ){
            $observer->update( $this );
        }
    }

    /**
     * Method used by the Observer to get the logging message
     * @return string
     */
    public function getLogMsg(){
        return $this->_logMsg;
    }

    /**
     * Method to be called when a concrete worker must log
     * @param $msg string
     */
    protected function _doLog( $msg ){
        $this->_logMsg = get_class( $this ) . " - " . print_r( $msg, true );
        $this->notify();
    }

}