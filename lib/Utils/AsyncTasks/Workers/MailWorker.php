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

class MailWorker extends AbstractWorker
{

    /**
     * @param AbstractElement $queueElement
     *
     * @return void
     * @throws EndQueueException
     * @throws Exception
     * @throws ReQueueException
     */
    public function process(AbstractElement $queueElement): void
    {
        if (!$queueElement instanceof QueueElement) {
            return;
        }

        $this->_checkForReQueueEnd($queueElement);

        $mail = $this->createMailer();

        $mail->isSMTP();

        $mail->Host = (string)$queueElement->params['Host'];
        $mail->Port = (int)$queueElement->params['port'];
        $mail->Sender = (string)$queueElement->params['sender'];
        $mail->Hostname = (string)$queueElement->params['hostname'];
        $mail->From = (string)$queueElement->params['from'];
        $mail->FromName = (string)$queueElement->params['fromName'];

        $mail->addReplyTo((string)$queueElement->params['returnPath'], $mail->FromName);

        $mail->Subject = (string)$queueElement->params['subject'];
        $mail->Body = (string)$queueElement->params['htmlBody'];

        $mail->msgHTML($mail->Body);

        $mail->AltBody = (string)$queueElement->params['altBody'];

        $mail->XMailer = 'Matecat Mailer';
        $mail->CharSet = 'UTF-8';
        $mail->isHTML();

        if (empty($queueElement->params['address'][0])) {
            $this->_doLog("--- (Worker " . $this->_workerPid . ") :  Mailer Error: You must provide at least one recipient email address.");
            $this->_doLog("--- (Worker " . $this->_workerPid . ") : Message could not be sent: \n\n" . $mail->AltBody);
            throw new EndQueueException(" Mailer Error: You must provide at least one recipient email address.");
        }

        /** @var array{0: string, 1: string} $address */
        $address = $queueElement->params['address'];
        $mail->addAddress($address[0], $address[1]);

        if (!$mail->send()) {
            $this->_doLog("--- (Worker " . $this->_workerPid . ") : Mailer Error: " . $mail->ErrorInfo);
            $this->_doLog("--- (Worker " . $this->_workerPid . ") : Message could not be sent: \n\n" . $mail->AltBody);
            throw new ReQueueException('Mailer Error: ' . $mail->ErrorInfo);
        }

        $this->_doLog("--- (Worker " . $this->_workerPid . ") : Message has been sent.");
    }

    protected function createMailer(): PHPMailer
    {
        return new PHPMailer();
    }

}