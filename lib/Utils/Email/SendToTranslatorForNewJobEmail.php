<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/02/2017
 * Time: 17:46
 */

namespace Email;


use Translators\JobsTranslatorsStruct;
use Users_UserStruct;

class SendToTranslatorForNewJobEmail extends SendToTranslatorAbstract {

    public function __construct( Users_UserStruct $user, JobsTranslatorsStruct $translator, $projectName ) {
        parent::__construct( $user, $translator, $projectName );
        $this->title       = "MateCat - Translation Job.";
        $this->_setTemplate( 'Translator/job_new_send_to_translator_content.html' );
        $this->_RoutesMethod = '\Routes::sendToTranslatorConfirm';
    }

}