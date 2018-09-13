<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/07/2018
 * Time: 15:07
 */

namespace  Email;

class BaseCommentEmail extends AbstractEmail {

    protected $user;
    protected $comment ;
    protected $url ;
    protected $project;

    public function __construct( $user, $comment, $url, $project, $job ) {

        $this->project = $project ;
        $this->user = $user ;
        $this->comment = $comment ;
        $this->url = $url ;
        $this->job = $job;
        $this->_setLayout('skeleton.html');
        $this->_setTemplate('Comment/action_on_a_comment.html');
    }

    public function send() {

        $recipient  = array( $this->user->email, $this->user->first_name );

        $this->doSend( $recipient, $this->title ,
                $this->_buildHTMLMessage(),
                $this->_buildTxtMessage( $this->_buildMessageContent() )
        );
    }

    protected function _getTemplateVariables() {
        $content = \Comments_CommentDao::placeholdContent( $this->comment->message );

        return [
                'user'    => $this->user->toArray(),
                'project' => $this->project,
                'job'     => $this->job,
                'comment' => $this->comment->toArray(),
                'url'     => $this->url . ",comment",
                'content' => $content
        ];
    }

}
