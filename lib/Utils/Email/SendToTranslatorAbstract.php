<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/04/17
 * Time: 22.14
 *
 */

namespace Email;


use DateTime;
use DateTimeZone;
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

        $translator->delivery_date =
                ( new Datetime( $translator->delivery_date ) )
                ->setTimezone( new DateTimeZone( $this->_offsetToTimeZone( $translator->job_owner_timezone ) ) )
                ->format( DateTime::RFC850 );

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

        $userRecipient = $this->translator->getUser()->getArrayCopy();
        if( !empty( $userRecipient[ 'uid' ] ) ){
            $userRecipient[ '_name' ] = $userRecipient[ 'first_name' ] . " " . $userRecipient[ 'last_name' ];
        } else {
            $userRecipient[ '_name' ] = $this->translator->email;
        }

        return [
                'sender'        => $this->user->toArray(),
                'user'          => $userRecipient,
                'email'         => $this->translator->email,
                'delivery_date' => $this->translator->delivery_date,
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

    protected function _offsetToTimeZone( $offset ) {
        $offset    = $offset * 60 * 60;
        $abbreviations_list = array_reverse( timezone_abbreviations_list() );
        foreach ( $abbreviations_list as $zone => $abbreviation ) {
            foreach ( $abbreviation as $city ) {
                if ( $city[ 'offset' ] == $offset && $city[ 'timezone_id' ] != null ) {
                    return $city[ 'timezone_id' ];
                }
            }
        }
        return 'UTC';
    }

}