<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/12/16
 * Time: 12.13
 *
 */

namespace API\V2;

class ActivityLogController extends KleinController {


    public function validateRequest() {

        //implement a validator if needed
        $filterArgs = array(
                'project_id' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW
                ),
                'password'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $postInput = (object)filter_var_array( $this->request->params(
                array(
                        'project_id',
                        'password',
                )
        ), $filterArgs );

        $this->name          = $postInput->name;
        $this->tm_key        = $postInput->tm_key;
        $this->downloadToken = $postInput->downloadToken;

        $this->TMService->setName( $postInput->name );
        $this->TMService->setTmKey( $postInput->tm_key );

    }

}