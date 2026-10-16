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
use Model\ProjectCreation\ProjectCreationError;
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
            // The Executor requeues a PDOException: a second run would create the project again.
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
     * @throws EndQueueException
     */
    protected function _publishResults(): void
    {
        if (!isset($this->projectStructure)) {
            $this->_doLog("Project creation failed before ProjectStructure was initialized; skipping result publication.");
            return;
        }

        // Every published result says how the creation ended: an id, or an error. A failure outside
        // ProjectManager::createProject()'s catch (the ProjectManager constructor, FilesStorageFactory::create())
        // records nothing, and creation_status would answer 200 with no project.
        if (empty($this->projectStructure->result['id_project']) && empty($this->projectStructure->result['errors'])) {
            $this->projectStructure->addError(ProjectCreationError::CREATION_FAILED->value, 'Project creation failed. Please try again.');
        }

        $idProject = $this->projectStructure->id_project;

        try {
            $this->publishProjectResults($this->projectStructure);
        } catch (Throwable $e) {
            // Ack, never requeue: a Redis error reaching Executor::main() as ConnectionException requeues the
            // message, and the second run deletes the project the first one committed (clearFailedProject()).
            throw new EndQueueException(
                "--- (Worker " . $this->_workerPid . ") :  result of project $idProject not published: " . $e->getMessage(),
                self::ERR_REQUEUE_END,
                $e
            );
        } finally {
            // Unset, not reset: the worker is long-lived, so an empty structure left here would make the isset() guard
            // above always true. The next message that fails before _createProject() assigns its own structure (empty
            // params, or a ProjectStructure constructor that throws) would then publish this empty one as
            // project_completed:0 with a null ppassword.
            // Known rare case: that failed message publishes nothing, so its own project_completed:<id> keeps the
            // pending payload written by ProjectQueue::sendProject(), and creation_status answers 202 until the TTL
            // expires. Where to look: empty params are logged and mailed by _createProject() ("empty params found"); a
            // constructor exception is logged by Executor::main() as "Caught a generic exception. SKIP Frame".
            unset($this->projectStructure);
        }

        $this->_doLog("Project creation completed: " . $idProject);
    }

    /**
     * @throws Exception
     */
    protected function createProjectManager(ProjectStructure $structure): ProjectManager
    {
        return new ProjectManager($structure, $this->database);
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