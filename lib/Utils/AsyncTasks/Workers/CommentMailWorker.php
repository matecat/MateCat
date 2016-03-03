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
use TaskRunner\Commons\Context;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;

use \PHPMailer, \AMQHandler;

class CommentMailWorker extends AbstractWorker {

    /**
     * Handler to the AMQ server and Redis Server
     *
     * @var \AMQHandler
     */
    protected $_queueHandler;

    /**
     * ErrMailWorker constructor.
     *
     * @param AMQHandler $queueHandler
     */
    public function __construct( AMQHandler $queueHandler ) {
        \Log::$fileName = 'user_mail.log';
        $this->_queueHandler = $queueHandler;
    }

    public function process( AbstractElement $queueElement, Context $queueContext ) {

        $mail = new PHPMailer();

        $mail->IsSMTP();
        $mail->Host     = $queueElement->params[ 'Host' ];
        $mail->Port     = $queueElement->params[ 'port' ];
        $mail->Sender   = $queueElement->params[ 'sender' ];
        $mail->Hostname = $queueElement->params[ 'hostname' ];

        $mail->From       = $queueElement->params[ 'from' ];
        $mail->FromName   = $queueElement->params[ 'fromName' ];
        $mail->ReturnPath = $queueElement->params[ 'returnPath' ];

        $mail->AddReplyTo( $mail->ReturnPath, $mail->FromName );

        $mail->Subject = $queueElement->params[ 'subject' ];
        $mail->Body    = $queueElement->params[ 'htmlBody' ];
        $mail->AltBody = $queueElement->params[ 'altBody' ];

        $mail->MsgHTML( $mail->Body );

        $mail->XMailer = 'MateCat Mailer';
        $mail->CharSet = 'UTF-8';
        $mail->IsHTML();
        $mail->AddAddress( $queueElement->params[ 'address' ][ 0 ], $queueElement->params[ 'address' ][ 1 ] );

        if ( !$mail->Send() ) {
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