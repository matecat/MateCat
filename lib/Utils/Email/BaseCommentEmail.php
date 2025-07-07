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
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use ReflectionException;

class BaseCommentEmail extends AbstractEmail {

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

    /**
     * BaseCommentEmail constructor.
     *
     * @param UserStruct              $user
     * @param CommentStruct           $comment
     * @param string                  $url
     * @param ShapelessConcreteStruct $project
     * @param JobStruct               $job
     */
    public function __construct( UserStruct $user, CommentStruct $comment, string $url, ShapelessConcreteStruct $project, JobStruct $job ) {

        $this->project = $project;
        $this->user    = $user;
        $this->comment = $comment;
        $this->url     = $url;
        $this->job     = $job;
        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplate( 'Comment/action_on_a_comment.html' );
    }

    /**
     * @throws Exception
     */
    public function send() {

        $recipient = [ $this->user->email, $this->user->first_name ];

        $this->doSend( $recipient, $this->title,
                $this->_buildHTMLMessage(),
                $this->_buildTxtMessage( $this->_buildMessageContent() )
        );
    }

    /**
     * @throws ReflectionException
     */
    protected function _getTemplateVariables(): array {
        $content = CommentDao::placeholdContent( $this->comment->message );

        return [
                'user'      => $this->user->toArray(),
                'project'   => $this->project,
                'job'       => $this->job,
                'commenter' => $this->comment->getFullName(true),
                'url'       => $this->url . ",comment",
                'content'   => $content
        ];
    }
}
