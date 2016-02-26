<?php

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
        $mail = new PHPMailer();

        $mail->IsSMTP();
        $mail->Host       = INIT::$SMTP_HOST ;
        $mail->Port       = INIT::$SMTP_PORT ;
        $mail->Sender     = INIT::$SMTP_SENDER ;
        $mail->Hostname   = INIT::$SMTP_HOSTNAME ;

        $mail->From       = INIT::$SMTP_SENDER ;
        $mail->FromName   = INIT::$MAILER_FROM_NAME ;
        $mail->ReturnPath = INIT::$MAILER_RETURN_PATH ;

        $mail->AddReplyTo( $mail->ReturnPath, $mail->FromName );

        $mail->Subject = $this->buildSubject();
        $mail->Body = $this->buildHTMLMessage();
        $mail->AltBody = $this->buildTextMessage();

        $mail->MsgHTML($mail->Body);

        $mail->XMailer  = 'Translated Mailer';
		$mail->CharSet = 'UTF-8';
		$mail->IsHTML();
        $mail->AddAddress( $this->user->email, $this->user->first_name );
        $mail->Send();
        Log::doLog('email sent');
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
