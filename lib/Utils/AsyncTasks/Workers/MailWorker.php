<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 29/02/16
 * Time: 19.01
 *
 */

namespace AsyncTasks\Workers;

use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;

use \PHPMailer;

class MailWorker extends AbstractWorker {

    /**
     * @param AbstractElement $queueElement
     *
     * @return bool
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws \phpmailerException
     */
    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );

        $mail = new PHPMailer();

        $mail->isSMTP();

        $mail->Host     = $queueElement->params[ 'Host' ];
        $mail->Port     = $queueElement->params[ 'port' ];
        $mail->Sender   = $queueElement->params[ 'sender' ];
        $mail->Hostname = $queueElement->params[ 'hostname' ];

        $mail->From       = $queueElement->params[ 'from' ];
        $mail->FromName   = $queueElement->params[ 'fromName' ];
        $mail->ReturnPath = $queueElement->params[ 'returnPath' ];

        $mail->addReplyTo( $mail->ReturnPath, $mail->FromName );

        $mail->Subject = $queueElement->params[ 'subject' ];
        $mail->Body    = $queueElement->params[ 'htmlBody' ];
        $mail->AltBody = $queueElement->params[ 'altBody' ];

        $mail->msgHTML( $mail->Body );

        $mail->XMailer = 'MateCat Mailer';
        $mail->CharSet = 'UTF-8';
        $mail->isHTML();

        if( empty( $queueElement->params[ 'address' ][ 0 ] ) ){
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Mailer Error: You must provide at least one recipient email address." );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Message could not be sent: \n\n" . $mail->AltBody );
            throw new EndQueueException( " Mailer Error: You must provide at least one recipient email address." );
        }

        $mail->addAddress( $queueElement->params[ 'address' ][ 0 ], $queueElement->params[ 'address' ][ 1 ] );

        if ( !$mail->send() ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Mailer Error: " . $mail->ErrorInfo );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Message could not be sent: \n\n" . $mail->AltBody );
            throw new ReQueueException( 'Mailer Error: ' . $mail->ErrorInfo );
        }

        $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Message has been sent." );

        return true;

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
}