<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/11/2016
 * Time: 17:06
 */

namespace Email;

use WorkerClient ;
use AMQHandler, Log ;
use INIT ;

abstract class AbstractEmail
{

    protected $_layout_path ;
    protected $_template_path ;

    /**
     * @return array
     */
    abstract protected function _getTemplateVariables();


    /**
     * @return mixed
     */
    abstract function send() ;

    protected function _setLayout( $layout ) {
        $this->_layout_path = INIT::$TEMPLATE_ROOT . '/Emails/' . $layout ;
    }

    protected function _setTemplate( $template ) {
        $this->_template_path = INIT::$TEMPLATE_ROOT . '/Emails/' . $template ;
    }

    /**
     * TODO: implement some kind of hook to improve testability
     *
     * @param $mailConf
     */
    protected function _enqueueEmailDelivery($mailConf) {
        WorkerClient::init( new AMQHandler() );
        \WorkerClient::enqueue(
            'MAIL',
            '\AsyncTasks\Workers\MailWorker',
            $mailConf,
            array( 'persistent' => WorkerClient::$_HANDLER->persistent )
        );

        Log::doLog( 'Message has been sent' );
    }

    protected function _buildMessageContent(){
        ob_start();
        extract( $this->_getTemplateVariables(), EXTR_OVERWRITE );
        include( $this->_template_path ) ;
        return ob_get_clean();
    }

    protected function _buildHTMLMessage(){
        ob_start();
        extract( $this->_getLayoutVariables(), EXTR_OVERWRITE );
        include( $this->_layout_path );
        return ob_get_clean();
    }

    protected function _getDefaultMailConf() {
        $mailConf = array();

        $mailConf[ 'Host' ]       = INIT::$SMTP_HOST;
        $mailConf[ 'port' ]       = INIT::$SMTP_PORT;
        $mailConf[ 'sender' ]     = INIT::$SMTP_SENDER;
        $mailConf[ 'hostname' ]   = INIT::$SMTP_HOSTNAME;
        $mailConf[ 'from' ]       = INIT::$SMTP_SENDER;
        $mailConf[ 'fromName' ]   = INIT::$MAILER_FROM_NAME;
        $mailConf[ 'returnPath' ] = INIT::$MAILER_RETURN_PATH;

        return $mailConf ;
    }

    protected function doSend($address, $subject, $htmlBody, $altBody) {
        $mailConf = $this->_getDefaultMailConf();

        $mailConf[ 'address' ] = $address ;
        $mailConf[ 'subject' ] = $subject ;

        $mailConf[ 'htmlBody' ] = $htmlBody ;
        $mailConf[ 'altBody' ]  = $altBody ;

        $this->_enqueueEmailDelivery( $mailConf );

        return true;
    }

    /**
     * @param $title
     * @param $messageBody
     *
     * @return string
     */
    protected function _buildTxtMessage( $messageBody ){
        $messageBody = preg_replace( "#<[/]*span[^>]*>#i", "\r\n", $messageBody );
        $messageBody = preg_replace( "#<[/]*(ol|ul|li)[^>]*>#i", "\r\n", $messageBody );
        return preg_replace( "#<br[^>]*>#i", "\r\n", $messageBody );
    }

    protected function _getLayoutVariables() {
        return array(
            'title' => 'MateCat',
            'messageBody' => $this->_buildMessageContent(),
            'closingLine' => "Kind regards, ",
            'showTitle' => false
        );
    }

}