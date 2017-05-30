<?php


namespace Features\Dqf\Worker ;

use API\V2\Exceptions\AuthenticationError;
use Features\Dqf\Model\ProjectCreation;
use Features\Dqf\Service\Struct\ProjectCreationStruct;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\QueueElement ;
use TaskRunner\Exceptions\EndQueueException ;

use TaskRunner\Commons\AbstractWorker;

class CreateProjectWorker extends AbstractWorker  {

    protected $sourceLanguageCode ;

    protected $reQueueNum = 10 ;

    /**
     * @var QueueElement
     */
    protected $queueElement ;

    public function process( AbstractElement $queueElement ) {
        $this->queueElement = $queueElement ;
        $this->_checkForReQueueEnd( $this->queueElement );
        $this->_checkDatabaseConnection() ;

        try {
            $struct = new ProjectCreationStruct( json_decode( $queueElement->params, true ) );
            (new ProjectCreation( $struct ))->process();
        }
        catch (AuthenticationError $e ) {
            throw new EndQueueException( $e->getMessage() ) ;
        }

    }

}