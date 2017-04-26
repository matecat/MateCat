<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/04/17
 * Time: 22.14
 *
 */

namespace Email;


use Translators\JobsTranslatorsStruct;
use Users_UserStruct;

abstract class SendToTranslatorAbstract extends AbstractEmail {

    protected $user;
    protected $projectName;
    protected $translator;
    protected $_RoutesMethod;

    public function __construct( Users_UserStruct $user, JobsTranslatorsStruct $translator, $projectName ) {

        $this->user        = $user;
        $this->translator  = $translator;
        $this->title       = "MateCat - Translation Job";
        $this->projectName = $projectName;

        $this->_setLayout( 'skeleton.html' );

    }

    public function send() {
        $recipient = array( $this->translator->email );

        //we need to get the bodyHtmlMessage only once because JWT changes if called more than once
        // otherwise html message will differ from the alternative text message
        $bodyHtmlMessage = $this->_buildMessageContent();

        $this->doSend( $recipient, $this->title,
                $this->_buildHTMLMessage( $bodyHtmlMessage ),
                $this->_buildTxtMessage( $bodyHtmlMessage )
        );
    }

    protected function _getTemplateVariables() {
        return [
                'sender'        => $this->user->toArray(),
                'user'          => $this->translator->getUser()->getArrayCopy(),
                'email'         => $this->translator->email,
                'delivery_date' => date( DATE_COOKIE, strtotime( $this->translator->delivery_date ) ),
                'project_url'   => call_user_func( $this->_RoutesMethod, [
                        'invited_by_uid' => $this->user->uid,
                        'email'          => $this->translator->email,
                        'project_name'   => $this->projectName,
                        'id_job'         => $this->translator->id_job,
                        'password'       => $this->translator->job_password,
                        'source'         => $this->translator->source,
                        'target'         => $this->translator->target
                ] )
        ];
    }

}