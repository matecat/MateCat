<?php

use TaskRunner\Commons\ContextList;
use TaskRunner\Commons\QueueElement;

class Comments_CommentEmail {

    private $user;
    private $comment ;
    private $url ;

    public function __construct( $user, $comment, $url, $project_name ) {
        $this->project_name = $project_name ;
        $this->user = $user ;
        $this->comment = $comment ;
        $this->url = $url ;
    }

    public function deliver() {

        $mailConf[ 'Host' ]       = INIT::$SMTP_HOST;
        $mailConf[ 'port' ]       = INIT::$SMTP_PORT;
        $mailConf[ 'sender' ]     = INIT::$SMTP_SENDER;
        $mailConf[ 'hostname' ]   = INIT::$SMTP_HOSTNAME;
        $mailConf[ 'from' ]       = INIT::$SMTP_SENDER;
        $mailConf[ 'fromName' ]   = INIT::$MAILER_FROM_NAME;
        $mailConf[ 'returnPath' ] = INIT::$MAILER_RETURN_PATH;

        $mailConf[ 'address' ]  = array( $this->user->email, $this->user->first_name );
        $mailConf[ 'subject' ]  = $this->buildSubject();
        $mailConf[ 'htmlBody' ] = $this->buildHTMLMessage();
        $mailConf[ 'altBody' ]  = $this->buildTextMessage();

        WorkerClient::init( new AMQHandler() );
        \WorkerClient::enqueue( 'MAIL', '\AsyncTasks\Workers\MailWorker', $mailConf, array( 'persistent' => WorkerClient::$_HANDLER->persistent ) );

        Log::doLog( 'Message has been sent' );
        return true;

    }

    private function buildHTMLMessage() {
        $link = $this->buildLink() ;
        return "<p> Hi {$this->user->first_name}, <br /> " .
            $this->verbalizeAction() .
            "</p>" .
            "<p>{$this->comment->message}</p>" .
            "<br />" .
            "<a href=\"{$link}\">{$link}</a>" ;
    }

    private function buildLink() {
        return $this->url . ',comment' ; // TODO: constantize `comment`
    }

    private function verbalizeAction() {
        if ( $this->comment->isComment() ) {
            $message = "%s added a comment to project %s at segment %s." ;
            return sprintf($message, $this->comment->full_name, $this->comment->id_job,
                $this->comment->id_segment);
        } else {
            $message = "%s marked the thread as resolved on project %s at segment %s.";
            return sprintf($message, $this->comment->full_name, $this->comment->id_job,
                $this->comment->id_segment);
        }
    }

    private function buildTextMessage() {
        return "Hi {$this->user->first_name}, " .
            $this->verbalizeAction() .
            "\n\n" .
            "{$this->comment->message}" .
            "\n\n" .
            "Visit the following URL to see the whole thread." .
            "{$this->buildLink()}" ;
    }

    private function buildSubject() {
        if ( $this->comment->isComment() ) {
            return "MateCat - {$this->project_name} - comment submitted";
        } else {
            return "MateCat - {$this->project_name} - thread resolved" ;
        }
    }

}
