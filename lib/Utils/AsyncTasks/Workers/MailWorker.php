<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 29/02/16
 * Time: 19.01
 *
 */

namespace Utils\AsyncTasks\Workers;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

class MailWorker extends AbstractWorker {

    /**
     * @param AbstractElement $queueElement
     *
     * @return bool|mixed
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws Exception
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
        $mail->From     = $queueElement->params[ 'from' ];
        $mail->FromName = $queueElement->params[ 'fromName' ];

        $mail->addReplyTo( $queueElement->params[ 'returnPath' ], $mail->FromName );

        $mail->Subject = $queueElement->params[ 'subject' ];
        $mail->Body    = $queueElement->params[ 'htmlBody' ];

        $mail->msgHTML( $mail->Body );

        $mail->AltBody = $queueElement->params[ 'altBody' ];

        $mail->XMailer = 'Matecat Mailer';
        $mail->CharSet = 'UTF-8';
        $mail->isHTML();

        if ( empty( $queueElement->params[ 'address' ][ 0 ] ) ) {
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

}