<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/10/16
 * Time: 18.03
 *
 */


class TmKeyManagement_ShareKeyEmail {

    protected $userMail = [];

    protected $keyStruct;

    protected $sender;

    protected $alreadyRegistered = false;

    public function __construct( Users_UserStruct $sender, Array $userMail, TmKeyManagement_MemoryKeyStruct $keyStruct ) {

        $this->userMail  = $userMail;
        $this->keyStruct = $keyStruct;
        $this->sender = $sender;

    }

    public function send(  ){

        $mailConf[ 'Host' ]       = INIT::$SMTP_HOST;
        $mailConf[ 'port' ]       = INIT::$SMTP_PORT;
        $mailConf[ 'sender' ]     = INIT::$SMTP_SENDER;
        $mailConf[ 'hostname' ]   = INIT::$SMTP_HOSTNAME;
        $mailConf[ 'from' ]       = INIT::$SMTP_SENDER;
        $mailConf[ 'fromName' ]   = INIT::$MAILER_FROM_NAME;
        $mailConf[ 'returnPath' ] = INIT::$MAILER_RETURN_PATH;

        $mailConf[ 'address' ] = array( $this->userMail[ 0 ], $this->userMail[ 1 ] );
        $mailConf[ 'subject' ] = "MateCat - Resource shared";

        $messageBody            = $this->_buildMessageContent();
        $mailConf[ 'htmlBody' ] = $this->_buildHTMLMessage( $mailConf[ 'subject' ], $messageBody );
        $mailConf[ 'altBody' ]  = $this->_buildTxtMessage( $messageBody );

        WorkerClient::init( new AMQHandler() );
        \WorkerClient::enqueue( 'MAIL', '\AsyncTasks\Workers\MailWorker', $mailConf, array( 'persistent' => WorkerClient::$_HANDLER->persistent ) );

        Log::doLog( 'Message has been sent' );

        return true;

    }

    protected function _buildMessageContent(){

        $params                     = [];
        $params[ "senderFullName" ] = $this->sender->fullName();
        $params[ "senderEmail" ]    = $this->sender->email;
        $params[ "tm_key_name" ]    = $this->keyStruct->tm_key->name;
        $params[ "tm_key_value" ]   = $this->keyStruct->tm_key->key;
        $params[ "addressMail" ]    = $this->userMail[ 0 ];

        ob_start();
        extract( $params, EXTR_OVERWRITE );
        include( INIT::$TEMPLATE_ROOT . "/Emails/ShareKey/message_content.html"  );
        return ob_get_clean();

    }

    /**
     * @param $title
     * @param $messageBody
     *
     * @return string
     */
    protected function _buildHTMLMessage( $title, $messageBody ){

        ob_start();
        include( INIT::$TEMPLATE_ROOT . "/Emails/ShareKey/skeleton.html"  );
        return ob_get_clean();

    }

    protected function _buildTxtMessage( $messageBody ){
        $messageBody = preg_replace( "#<[/]*span[^>]*>#i", "\r\n", $messageBody );
        $messageBody = preg_replace( "#<[/]*(ol|ul|li)[^>]*>#i", "\r\n", $messageBody );
        return preg_replace( "#<br[^>]*>#i", "\r\n", $messageBody );
    }

}