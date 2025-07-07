<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 12.19
 *
 */

namespace Utils\AsyncTasks\Workers;


use Exception;
use PDOException;
use ProjectManager;
use RecursiveArrayObject;
use ReflectionException;
use Utils\ProjectQueue\Queue;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

class ProjectCreationWorker extends AbstractWorker {

    protected $projectStructure;

    const PROJECT_HASH = 'project_queue:%u';

    /**
     * @param AbstractElement $queueElement
     *
     * @return void
     * @throws EndQueueException
     * @throws Exception
     */
    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );
        $this->_checkDatabaseConnection();

        try {
            $this->_createProject( $queueElement );
        } catch ( PDOException $e ) {
            throw new EndQueueException( $e );
        } finally {
            $this->_publishResults();
        }

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
    protected function _createProject( QueueElement $queueElement ) {

        if ( empty( $queueElement->params ) ) {
            $msg = "\n\n Error Project Creation  \n\n " . var_export( $queueElement, true );
            \Utils::sendErrMailReport( $msg );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  empty params found." );
            throw new EndQueueException( "--- (Worker " . $this->_workerPid . ") :  empty params found.", self::ERR_REQUEUE_END );
        }

        $this->projectStructure = new RecursiveArrayObject( $queueElement->params->toArray() );
        $projectManager         = new ProjectManager( $this->projectStructure );
        $projectManager->createProject();

    }

    /**
     * @throws ReflectionException
     */
    protected function _publishResults() {
        Queue::publishResults( $this->projectStructure );
        $this->_doLog( "Project creation completed: " . $this->projectStructure[ 'id_project' ] );
        $this->projectStructure = new RecursiveArrayObject();
    }

}