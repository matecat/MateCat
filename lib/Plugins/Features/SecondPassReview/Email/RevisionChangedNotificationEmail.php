<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 11/06/2019
 * Time: 13:06
 */

namespace Features\SecondPassReview\Email ;

use Email\AbstractEmail;

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

    public function __construct( $recipientUser, $segmentUrl, $changeAuthor = null ) {
        $this->recipientUser = $recipientUser ;
        $this->segmentUrl    = $segmentUrl ;
        $this->changeAuthor  = $changeAuthor ;

        $this->_setlayout( 'skeleton.html' );
        $this->_settemplate( 'Revise/second_pass_segment_changed_notice.html' );
    }

    protected function _getTemplateVariables() {
        return [
                'changeAuthor' => ( $this->changeAuthor ? $this->changeAuthor->toArray() : null ),
                'recipientUser' => $this->recipientUser->toArray(),
                'segmentUrl' => $this->segmentUrl
        ] ;
    }

    public function send()
    {
        $this->sendTo($this->recipientUser->email, $this->recipientUser->fullName() );
    }

}