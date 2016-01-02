<?php

use Analysis\DqfQueueHandler;

/**
 * Class checkTausPMKeyController
 */
class checkTausPMKeyController extends ajaxController {

    protected $DQF_PMANAGER_KEY;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'DQF_PMANAGER_KEY' => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                )
        );

        $postInput    = filter_input_array( INPUT_POST, $filterArgs );
        $this->DQF_PMANAGER_KEY = $postInput[ 'DQF_PMANAGER_KEY' ];

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    public function doAction() {

        $dqfQueue = new DqfQueueHandler();

        try {

            $projectManagerInfo     = $dqfQueue->checkProjectManagerKey( $this->DQF_PMANAGER_KEY );
            $this->result[ 'data' ] = 'OK';

        } catch ( Exception $e ) {

            $this->result[ 'data' ]     = 'KO';
            $this->result[ 'errors' ][] = array(
                    'code'    => -1,
                    'message' => $e->getMessage()
            );

        }

    }

}