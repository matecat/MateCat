<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 24/04/17
 * Time: 19.50
 *
 */

namespace Translators;


use Exception;
use Jobs_JobStruct;

class DetachedTranslatorsModel extends TranslatorsModel {

    /**
     * This model makes override of the constructor to be used without a controller.
     * Method update is not allowed.
     *
     * Use TranslatorsModel instead.
     *
     * DetachedTranslatorsModel constructor.
     *
     * @param Jobs_JobStruct $jStruct
     */
    public function __construct( Jobs_JobStruct $jStruct ) {
        //get the job
        $this->jStruct = $jStruct;
    }

    public function update(){
        throw new Exception( "Update method not allowed.");
    }

    public function setEmailChange( $email ){
        $this->mailsToBeSent[ 'change' ] = $email;
    }

}