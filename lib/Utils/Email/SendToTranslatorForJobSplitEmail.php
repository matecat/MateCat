<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/02/2017
 * Time: 17:46
 */

namespace Utils\Email;


use Model\Translators\JobsTranslatorsStruct;
use Model\Users\UserStruct;
use Routes;

class SendToTranslatorForJobSplitEmail extends SendToTranslatorAbstract {

    public function __construct( UserStruct $user, JobsTranslatorsStruct $translator, $projectName ) {
        parent::__construct( $user, $translator, $projectName );
        $this->title = "Matecat - Job delivery updated.";
        $this->_setTemplate( 'Translator/job_split_send_to_translator_content.html' );
        $this->_RoutesMethod = [ Routes::class, 'translate' ];
    }

}