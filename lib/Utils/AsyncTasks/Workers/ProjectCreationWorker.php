<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 12.19
 *
 */

namespace Utils\AsyncTasks\Workers;


use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\ProjectCreation\ProjectManager;
use Model\ProjectCreation\ProjectStructure;
use PDOException;
use ReflectionException;
use Throwable;
use Utils\ActiveMQ\ClientHelpers\ProjectQueue;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\Tools\Utils;

class ProjectCreationWorker extends AbstractWorker
{

    protected ProjectStructure $projectStructure;

    /**
     * @param AbstractElement $queueElement
     *
     * @return void
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Throwable
     * @throws ValidationError
     */
    public function process(AbstractElement $queueElement): void
    {
        if (!$queueElement instanceof QueueElement) {
            return;
        }

        $this->_checkForReQueueEnd($queueElement);
        $this->_checkDatabaseConnection();

        try {
            $this->_createProject($queueElement);
        } catch (PDOException $e) {
            throw new EndQueueException($e);
        } finally {
            $this->_publishResults();
        }
    }

    /**
     * @throws EndQueueException
     * @throws Exception
     */
    protected function _checkForReQueueEnd(QueueElement $queueElement): void
    {
        /**
         *
         * check for loop re-queuing
         */
        if ($queueElement->reQueueNum >= 100) {
            $msg = "\n\n Error Project Creation  \n\n " . var_export($queueElement, true);
            Utils::sendErrMailReport($msg);
            $this->_doLog("--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.");
            throw new EndQueueException("--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END);
        } elseif ($queueElement->reQueueNum > 0) {
//            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }
    }

    /**
     * @param QueueElement $queueElement
     *
     * @throws EndQueueException
     * @throws Throwable
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ValidationError
     */
    protected function _createProject(QueueElement $queueElement): void
    {
        if (empty($queueElement->params)) {
            $msg = "\n\n Error Project Creation  \n\n " . var_export($queueElement, true);
            Utils::sendErrMailReport($msg);
            $this->_doLog("--- (Worker " . $this->_workerPid . ") :  empty params found.");
            throw new EndQueueException("--- (Worker " . $this->_workerPid . ") :  empty params found.", self::ERR_REQUEUE_END);
        }

        $this->projectStructure = new ProjectStructure($queueElement->params->toArray());
        $projectManager = $this->createProjectManager($this->projectStructure);
        $projectManager->createProject();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _publishResults(): void
    {
        if (!isset($this->projectStructure)) {
            $this->_doLog("Project creation failed before ProjectStructure was initialized; skipping result publication.");
            return;
        }

        $this->publishProjectResults($this->projectStructure);
        $this->_doLog("Project creation completed: " . $this->projectStructure->id_project);
        $this->projectStructure = new ProjectStructure();
    }

    /**
     * @throws Exception
     */
    protected function createProjectManager(ProjectStructure $structure): ProjectManager
    {
        return new ProjectManager($structure);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function publishProjectResults(ProjectStructure $structure): void
    {
        ProjectQueue::publishResults($structure);
    }

}