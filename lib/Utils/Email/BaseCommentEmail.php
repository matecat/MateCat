<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/07/2018
 * Time: 15:07
 */

namespace Email;

use Model\Comments\CommentStruct;
use Users_UserStruct;

class BaseCommentEmail extends AbstractEmail {

    /**
     * @var Users_UserStruct
     */
    protected $user;

    /**
     * @var CommentStruct
     */
    protected CommentStruct $comment;

    /**
     * @var string
     */
    protected $url;

    protected $project;

    protected $job;

    /**
     * BaseCommentEmail constructor.
     *
     * @param Users_UserStruct $user
     * @param CommentStruct    $comment
     * @param $url
     * @param $project
     * @param $job
     */
    public function __construct( Users_UserStruct $user, CommentStruct $comment, $url, $project, $job ) {

        $this->project = $project;
        $this->user    = $user;
        $this->comment = $comment;
        $this->url     = $url;
        $this->job     = $job;
        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplate( 'Comment/action_on_a_comment.html' );
    }

    public function send() {

        $recipient = [ $this->user->email, $this->user->first_name ];

        $this->doSend( $recipient, $this->title,
                $this->_buildHTMLMessage(),
                $this->_buildTxtMessage( $this->_buildMessageContent() )
        );
    }

    protected function _getTemplateVariables(): array {
        $content = \Model\Comments\CommentDao::placeholdContent( $this->comment->message );

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
