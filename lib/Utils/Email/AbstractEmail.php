<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/11/2016
 * Time: 17:06
 */

namespace Utils\Email;

use Exception;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\MailWorker;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;

abstract class AbstractEmail {

    protected ?string $title = null;

    protected string $_layout_path;
    protected string $_template_path;

    /**
     * @return array
     */
    abstract protected function _getTemplateVariables(): array;


    /**
     * @return mixed
     */
    abstract function send();

    protected function _setLayout( string $layout ) {
        $this->_layout_path = AppConfig::$TEMPLATE_ROOT . '/Emails/' . $layout;
    }

    protected function _setLayoutByPath( string $path ) {
        $this->_layout_path = $path;
    }

    protected function _setTemplate( string $template ) {
        $this->_template_path = AppConfig::$TEMPLATE_ROOT . '/Emails/' . $template;
    }

    protected function _setTemplateByPath( string $path ) {
        $this->_template_path = $path;
    }

    /**
     *
     * @param array $mailConf
     *
     */
    protected function _enqueueEmailDelivery( array $mailConf ) {
        WorkerClient::enqueue(
                'MAIL',
                MailWorker::class,
                $mailConf,
                [ 'persistent' => WorkerClient::$_HANDLER->persistent ]
        );

        LoggerFactory::doJsonLog( 'Message has been sent' );
    }

    /**
     * @return string
     */
    protected function _buildMessageContent(): string {
        ob_start();
        extract( $this->_getTemplateVariables() );
        include( $this->_template_path );

        return ob_get_clean();
    }

    /**
     * @param string|null $messageContent
     *
     * @return string
     */
    protected function _buildHTMLMessage( ?string $messageContent = null ): string {
        ob_start();
        extract( $this->_getLayoutVariables( $messageContent ) );
        include( $this->_layout_path );

        return ob_get_clean();
    }

    /**
     * @param string|null $messageBody
     *
     * @return array
     */
    protected function _getLayoutVariables( ?string $messageBody = null ): array {

        return [
                'title'       => $this->title ?? 'Matecat',
                'messageBody' => ( !empty( $messageBody ) ? $messageBody : $this->_buildMessageContent() ),
                'closingLine' => "Kind regards, ",
                'showTitle'   => false
        ];
    }

    /**
     * @return array
     */
    protected function _getDefaultMailConf(): array {

        $mailConf = [];

        $mailConf[ 'Host' ]     = AppConfig::$SMTP_HOST;
        $mailConf[ 'port' ]     = AppConfig::$SMTP_PORT;
        $mailConf[ 'sender' ]   = AppConfig::$SMTP_SENDER;
        $mailConf[ 'hostname' ] = AppConfig::$SMTP_HOSTNAME;

        $mailConf[ 'from' ]       = AppConfig::$SMTP_SENDER;
        $mailConf[ 'fromName' ]   = AppConfig::$MAILER_FROM_NAME;
        $mailConf[ 'returnPath' ] = AppConfig::$MAILER_RETURN_PATH;

        return $mailConf;

    }

    /**
     * @throws Exception
     */
    protected function sendTo( string $address, string $name ) {
        $recipient = [ $address, $name ];

        $this->doSend( $recipient, $this->title,
                $this->_buildHTMLMessage(),
                $this->_buildTxtMessage( $this->_buildMessageContent() )
        );
    }

    /**
     * @throws Exception
     */
    protected function doSend( array $address, string $subject, string $htmlBody, string $altBody ): bool {
        $mailConf = $this->_getDefaultMailConf();

        $mailConf[ 'address' ] = $address;
        $mailConf[ 'subject' ] = $subject;

        $mailConf[ 'htmlBody' ] = $htmlBody;
        $mailConf[ 'altBody' ]  = $altBody;

        $this->_enqueueEmailDelivery( $mailConf );

        return true;
    }

    /**
     * @param $messageBody
     *
     * @return string
     * @internal param $title
     */
    protected function _buildTxtMessage( $messageBody ): string {
        $messageBody = preg_replace( "#<[/]*span[^>]*>#i", "", $messageBody );
        $messageBody = preg_replace( "#<[/]*strong[^>]*>#i", "", $messageBody );
        $messageBody = preg_replace( "#<[/]*(ol|ul|li)[^>]*>#i", "\t", $messageBody );
        $messageBody = preg_replace( "#<[/]*(p)[^>]*>#i", "", $messageBody );
        $messageBody = preg_replace( "#<a.*?href=[\"'](.*)[\"'][^>]*>(.*?)</a>#i", "$2 $1", $messageBody );
        $messageBody = html_entity_decode( $messageBody );

        return preg_replace( "#<br[^>]*>#i", "\r\n", $messageBody );
    }

}