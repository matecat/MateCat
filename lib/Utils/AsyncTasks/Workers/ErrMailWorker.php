<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/02/16
 * Time: 20.05
 *
 */

namespace AsyncTasks\Workers;

use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\Params;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EmptyElementException;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;

use \PHPMailer;

/**
 * Class TMAnalysisWorker
 * @package Analysis\Workers
 *
 * Concrete worker.
 * This worker handle a queue element ( a segment ) and perform the analysis on it
 */
class ErrMailWorker extends AbstractWorker {

    /**
     * Override to set another logger on the same queue
     * 
     * @return string
     */
    public function getLoggerName(){
        return "err_mail.log";
    }

    /**
     * Concrete Method to start the activity of the worker
     *
     * @param AbstractElement $queueElement
     *
     * @return void
     *
     * @throws EmptyElementException
     * @throws EndQueueException
     * @throws ReQueueException
     */
    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );

        $this->_sendErrMailReport( $queueElement->params );

    }

    /**
     * Check how much times the element was re-queued and raise an Exception when the limit is reached ( 100 times )
     *
     * @param QueueElement $queueElement
     *
     * @throws EndQueueException
     */
    protected function _checkForReQueueEnd( QueueElement $queueElement ){

        /**
         *
         * check for loop re-queuing
         */
        if ( isset( $queueElement->reQueueNum ) && $queueElement->reQueueNum >= 100 ) {

            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Frame Re-queue max value reached, acknowledge and skip." );
            throw new EndQueueException( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END );

        } elseif ( isset( $queueElement->reQueueNum ) ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }

    }


    protected function _sendErrMailReport( Params $mailConf ){

        if( empty( $mailConf->server_configuration ) ){

            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Wrong configuration data found. Ensure that 'TaskRunner\\Commons\\Params->server_configuration' exists and contains valid data." );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Message not sent." );
            throw new EmptyElementException( "No eMail in configuration file found. Ensure that 'TaskRunner\\Commons\\Params->server_configuration' exists and contains valid data." );

        } else {

            $mail = new PHPMailer();

            $mail->isSMTP();
            $mail->Host       = $mailConf->server_configuration['Host'];
            $mail->Port       = $mailConf->server_configuration['Port'];
            $mail->Sender     = $mailConf->server_configuration['Sender'];
            $mail->Hostname   = $mailConf->server_configuration['Hostname'];

            $mail->From       = $mailConf->server_configuration['From'];
            $mail->FromName   = $mailConf->server_configuration['FromName'];
            $mail->ReturnPath = $mailConf->server_configuration['ReturnPath'];
            $mail->addReplyTo( $mail->ReturnPath, $mail->FromName );

            if( !empty( $mailConf->email_list ) ){
                foreach( $mailConf->email_list as $email => $uName ){
                    $mail->addAddress( $email, $uName );
                }
            } else{
                $this->_doLog( "--- (Worker " . $this->_workerPid . ") : No eMail list found. Ensure that 'TaskRunner\\Commons\\Params->email_list' exists and contains a valid mail list. One per row." );
                throw new EmptyElementException( "No eMail list found. Ensure that 'TaskRunner\\Commons\\Params->email_list' exists and contains a valid mail list. One per row." );
            }

        }

        $mail->XMailer  = 'MateCat Mailer';
        $mail->CharSet = 'UTF-8';
        $mail->isHTML();

        /*
         *
         * "X-Priority",
         *  "1″ This is the most common way of setting the priority of an email.
         *  "3″ is normal, and "5″ is the lowest.
         *  "2″ and "4″ are in-between, and frankly.
         *
         *  I’ve never seen anything but "1" or "3" used.
         *
         * Microsoft Outlook adds these header fields when setting a message to High priority:
         *
         * X-Priority: 1 (Highest)
         * X-MSMail-Priority: High
         * Importance: High
         *
         */
        $mail->Priority = 1;

        if( empty( $mailConf->subject ) ){
            $mail->Subject = 'Alert from Matecat: ' . php_uname('n');
        } else {
            $mail->Subject = $mailConf->subject;
        }

        $mail->Body    = '<pre>' . $mailConf->body . '</pre>';

        $txtContent = preg_replace(  '|<br[\x{20}/]*>|ui', "\n\n", $mailConf->body );
        $mail->AltBody = strip_tags( $txtContent );

        $mail->msgHTML($mail->Body);

        if(!$mail->send()) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Mailer Error: " . $mail->ErrorInfo );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Message could not be sent: \n\n" . $mail->AltBody );
            throw new ReQueueException( 'Mailer Error: ' . $mail->ErrorInfo );
        }

        $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Message has been sent." );
        return true;

    }

}
