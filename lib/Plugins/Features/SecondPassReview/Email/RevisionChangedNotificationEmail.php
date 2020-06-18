<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/06/2019
 * Time: 13:06
 */

namespace Features\SecondPassReview\Email ;

use Email\AbstractEmail;
use Users_UserStruct;

class RevisionChangedNotificationEmail extends AbstractEmail {

    /**
     * @var \Users_UserStruct
     */
    protected $changeAuthor ;
    protected $segmentUrl ;
    /**
     * @var \Users_UserStruct
     */
    protected $recipientUser ;

    protected $title = 'Revised segment changed' ;
    protected $data;
    protected $_segmentInfo;

    public function __construct( $segmentInfo, $data, $segmentUrl, $changeAuthor = null ) {
        $this->_segmentInfo  = $segmentInfo ;
        $this->data          = $data ;
        $this->recipientUser = $data['recipient'];
        $this->segmentUrl    = $segmentUrl ;
        $this->changeAuthor  = $changeAuthor ;

        $this->_setlayout( 'skeleton.html' );
        $this->_settemplate( 'Revise/second_pass_segment_changed_notice.html' );
    }

    protected function _getTemplateVariables() {
        return [
                'changeAuthor'  => ( $this->changeAuthor ? $this->changeAuthor->toArray() : null ),
                'recipientUser' => $this->data['recipient']->toArray(),
                'segmentUrl'    => $this->segmentUrl,
                'data'          => $this->data,
                'segmentInfo'   => $this->_segmentInfo
        ] ;
    }

    public function send()
    {
        if(false === $this->isRecipientTheChangeAuthor($this->recipientUser->email, $this->changeAuthor)){
            $this->sendTo($this->recipientUser->email, $this->recipientUser->fullName() );
        }
    }

    /**
     * @param string                $email
     * @param Users_UserStruct|null $user
     *
     * @return bool
     */
    private function isRecipientTheChangeAuthor( $email, Users_UserStruct $user = null ) {
        if ( null === $user ) {
            return false;
        }

        return $user->getEmail() === $email;
    }
}