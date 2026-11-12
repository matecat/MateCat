<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/07/2018
 * Time: 15:07
 */

namespace Utils\Email;

use Exception;
use Model\Comments\CommentDao;
use Model\Comments\CommentStruct;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use Throwable;

class BaseCommentEmail extends AbstractEmail
{

    /**
     * @var UserStruct
     */
    protected UserStruct $user;

    /**
     * @var CommentStruct
     */
    protected CommentStruct $comment;

    /**
     * @var string
     */
    protected string $url;

    protected ShapelessConcreteStruct $project;

    protected JobStruct $job;

    protected IDatabase $database;

    /**
     * BaseCommentEmail constructor.
     *
     * @param UserStruct $user
     * @param CommentStruct $comment
     * @param string $url
     * @param ShapelessConcreteStruct $project
     * @param JobStruct $job
     * @param IDatabase $database
     */
    public function __construct(UserStruct $user, CommentStruct $comment, string $url, ShapelessConcreteStruct $project, JobStruct $job, IDatabase $database)
    {
        $this->project = $project;
        $this->user = $user;
        $this->comment = $comment;
        $this->url = $url;
        $this->job = $job;
        $this->database = $database;
        $this->_setLayout('skeleton.html');
        $this->_setTemplate('Comment/action_on_a_comment.html');
    }

    /**
     * @throws Throwable
     */
    public function send(): void
    {
        $this->sendTo($this->user->email, $this->user->first_name);
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     * @throws ReflectionException
     * @throws RuntimeException
     */
    protected function _getTemplateVariables(): array
    {
        $message = $this->comment->message ?? throw new RuntimeException('Comment message is required to build email content');
        $content = (new CommentDao($this->database))->placeholdContent($message);

        return [
            'user' => $this->user->toArray(),
            // Only what the template prints. The project row comes from ProjectDao::getProjectData(),
            // whose `id` is the id of a file: the project id is the job's.
            'project' => ['id' => $this->job->id_project, 'name' => $this->project->name],
            'job' => ['source' => $this->job->source, 'target' => $this->job->target],
            'commenter' => $this->comment->getFullName(true),
            'url' => $this->url . ",comment",
            'content' => $content
        ];
    }
}
