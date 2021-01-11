<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 12.19
 *
 */

namespace AsyncTasks\Workers;


use PDOException;
use ProjectManager;
use ProjectQueue\Queue;
use RecursiveArrayObject;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;

class ProjectCreationWorker extends AbstractWorker {

    protected $projectStructure;

    const PROJECT_HASH = 'project_queue:%u';

    /**
     * @param AbstractElement $queueElement
     *
     * @return mixed|void
     * @throws EndQueueException
     */
    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );
        $this->_checkDatabaseConnection();

        try {
            $this->_createProject( $queueElement );
        } catch( PDOException $e ){
            throw new EndQueueException( $e );
        }

        $this->_publishResults();

    }

    protected function _checkForReQueueEnd( QueueElement $queueElement ) {

        /**
         *
         * check for loop re-queuing
         */
        if ( isset( $queueElement->reQueueNum ) && $queueElement->reQueueNum >= 100 ) {

            $msg = "\n\n Error Project Creation  \n\n " . var_export( $queueElement, true );
            \Utils::sendErrMailReport( $msg );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip." );
            throw new EndQueueException( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END );

        } elseif ( isset( $queueElement->reQueueNum ) ) {
//            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }

    }

    /**
     * @param QueueElement $queueElement
     *
     * @throws \Exception
     */
    protected function _createProject( QueueElement $queueElement ){

        if( empty( $queueElement->params ) ){
            $msg = "\n\n Error Project Creation  \n\n " . var_export( $queueElement, true );
            \Utils::sendErrMailReport( $msg );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  empty params found." );
            throw new EndQueueException( "--- (Worker " . $this->_workerPid . ") :  empty params found.", self::ERR_REQUEUE_END );
        }

        $this->projectStructure = new RecursiveArrayObject( json_decode( $queueElement->params, true ) );
        $projectManager = new ProjectManager( $this->projectStructure );
        $projectManager->createProject();

    }

    protected function _publishResults(){
        Queue::publishResults( $this->projectStructure );
        $this->_doLog( "Project creation completed: " . $this->projectStructure[ 'id_project' ] );
        $this->projectStructure = new RecursiveArrayObject();
    }

}