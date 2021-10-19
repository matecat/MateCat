<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 06/12/13
 * Time: 15.55
 *
 */

class glossaryController extends ajaxController {

    const GLOSSARY_WRITE = 'GLOSSARY_WRITE';
    const GLOSSARY_READ  = 'GLOSSARY_READ';
    /**
     * @var string
     */
    protected $id_client;

    private $exec;
    private $id_job;
    private $password;
    private $segment;
    private $newsegment;
    private $translation;
    private $newtranslation;
    private $comment;
    private $id_match;
    /**
     * @var Engines_MyMemory
     */
    private $_TMS;

    /**
     * @var Jobs_JobStruct
     */
    private $jobData;
    private $fromtarget;

    private $projectFeaturesString;

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'exec'             => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                'id_job'           => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'         => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                'current_password' => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
                'segment'          => [ 'filter' => FILTER_UNSAFE_RAW ],
                'newsegment'       => [ 'filter' => FILTER_UNSAFE_RAW ],
                'translation'      => [ 'filter' => FILTER_UNSAFE_RAW ],
                'from_target'      => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'newtranslation'   => [ 'filter' => FILTER_UNSAFE_RAW ],
                'comment'          => [ 'filter' => FILTER_UNSAFE_RAW ],
                'id'               => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_segment'       => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_client'        => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ],
        ];

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );
        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->exec              = $__postInput[ 'exec' ];
        $this->id_job            = $__postInput[ 'id_job' ];
        $this->password          = $__postInput[ 'password' ];
        $this->received_password = $__postInput[ 'current_password' ];
        $this->segment           = $__postInput[ 'segment' ];
        $this->newsegment        = $__postInput[ 'newsegment' ];
        $this->translation       = $__postInput[ 'translation' ];
        $this->fromtarget        = $__postInput[ 'from_target' ];
        $this->newtranslation    = $__postInput[ 'newtranslation' ];
        $this->comment           = $__postInput[ 'comment' ];
        $this->id_match          = $__postInput[ 'id' ];
        $this->id_client         = $__postInput[ 'id_client' ];
        $this->id_segment        = $__postInput[ 'id_segment' ];
    }

    public function doAction() {

        //get Job Info, we need only a row of jobs ( split )
        $this->jobData = Jobs_JobDao::getByIdAndPassword( (int)$this->id_job, $this->password );
        $this->featureSet->loadForProject( $this->jobData->getProject() );
        $this->projectFeaturesString = $this->jobData->getProject()->getMetadataValue( Projects_MetadataDao::FEATURES_KEY );

        /**
         * For future reminder
         *
         * MyMemory (id=1) should not be the only Glossary provider
         *
         */
        $this->_TMS = Engine::getInstance( 1 );
        $this->_TMS->setFeatureSet( $this->featureSet );

        $this->readLoginInfo();

        try {

            $config = $this->_TMS->getConfigStruct();

            // segment related
            $config[ 'segment' ]     = strip_tags( html_entity_decode( $this->segment ) );
            $config[ 'translation' ] = $this->translation;
            $config[ 'tnote' ]       = $this->comment;

            // job related
            $config[ 'id_user' ] = [];
            if ( $this->fromtarget ) { //Search by target
                $config[ 'source' ] = $this->jobData[ 'target' ];
                $config[ 'target' ] = $this->jobData[ 'source' ];
            } else {
                $config[ 'source' ] = $this->jobData[ 'source' ];
                $config[ 'target' ] = $this->jobData[ 'target' ];
            }
            $config[ 'isGlossary' ] = true;
            $config[ 'get_mt' ]     = null;
            $config[ 'email' ]      = INIT::$MYMEMORY_API_KEY;
            $config[ 'num_result' ] = 100; //do not want limit the results from glossary: set as a big number

            if ( $this->newsegment && $this->newtranslation ) {
                $config[ 'newsegment' ]     = $this->newsegment;
                $config[ 'newtranslation' ] = $this->newtranslation;
            }

            if ( $this->translation && $this->segment ) {
                $config[ 'translation' ]     = $this->translation;
                $config[ 'segment' ] = $this->segment;
            }

            switch ( $this->exec ) {

                case 'get':
                    $this->_get( $config );
                    break;
                case 'set':
                    /**
                     * For future reminder
                     *
                     * MyMemory should not be the only Glossary provider
                     *
                     */
                    if ( $this->jobData[ 'id_tms' ] == 0 ) {
                        throw new Exception( "Glossary is not available when the TM feature is disabled", -11 );
                    }
                    $this->_set( $config );
                    break;
                case 'update':
                    $this->_update( $config );
                    break;
                case 'delete':
                    $this->_delete( $config );
                    break;
            }

        } catch ( Exception $e ) {
            $this->result[ 'errors' ][] = [ "code" => $e->getCode(), "message" => $e->getMessage() ];
        }

    }

    /**
     * @param $config
     *
     * @throws ReflectionException
     */
    protected function _get( $config ) {

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        }

        $params = [
                'action'  => 'get',
                'payload' => [
                        'id_segment'     => $this->id_segment,
                        'id_client'      => $this->id_client,
                        'tm_keys'        => $this->jobData[ 'tm_keys' ],
                        'userRole'       => $this->userRole,
                        'user'           => $this->user->toArray(),
                        'featuresString' => $this->projectFeaturesString,
                        'jobData'        => $this->jobData->toArray(),
                        'tmProps'        => $this->jobData->getTMProps(),
                        'config'         => $config,
                        'segment'        => $this->segment,
                        'userIsLogged'   => $this->userIsLogged,
                        'fromtarget'     => $this->fromtarget,
                ],
        ];

        $this->enqueueWorker( self::GLOSSARY_READ, $params );
    }

    /**
     * @param $config
     *
     * @throws ReflectionException
     */
    protected function _set( $config ) {

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        }

        $params = [
                'action'  => 'set',
                'payload' => [
                    'id_segment'     => $this->id_segment,
                    'id_client'      => $this->id_client,
                    'tm_keys'        => $this->jobData[ 'tm_keys' ],
                    'userRole'       => $this->userRole,
                    'user'           => $this->user->toArray(),
                    'featuresString' => $this->projectFeaturesString,
                    'jobData'        => $this->jobData->toArray(),
                    'tmProps'        => $this->jobData->getTMProps(),
                    'config'         => $config,
                    'id_job'         => $this->id_job,
                    'password'       => $this->password,
                    'userIsLogged'   => $this->userIsLogged,
                ],
        ];

        $this->enqueueWorker( self::GLOSSARY_WRITE, $params );
    }

    /**
     * @param $config
     *
     * @throws ReflectionException
     */
    protected function _update( $config ) {

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        }

        $params = [
            'action'  => 'update',
            'payload' => [
                'id_segment'     => $this->id_segment,
                'id_client'      => $this->id_client,
                'tm_keys'        => $this->jobData[ 'tm_keys' ],
                'userRole'       => $this->userRole,
                'user'           => $this->user->toArray(),
                'featuresString' => $this->projectFeaturesString,
                'featureSet'     => $this->featureSet,
                'jobData'        => $this->jobData->toArray(),
                'tmProps'        => $this->jobData->getTMProps(),
                'config'         => $config,
            ],
        ];

        $this->enqueueWorker( self::GLOSSARY_WRITE, $params );
    }

    /**
     * @param $config
     *
     * @throws ReflectionException
     */
    protected function _delete( $config ) {

        if ( self::isRevision() ) {
            $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
        }

        $params = [
                'action'  => 'delete',
                'payload' => [
                        'id_segment'     => $this->id_segment,
                        'id_client'      => $this->id_client,
                        'tm_keys'        => $this->jobData[ 'tm_keys' ],
                        'userRole'       => $this->userRole,
                        'user'           => $this->user->toArray(),
                        'featuresString' => $this->projectFeaturesString,
                        'jobData'        => $this->jobData->toArray(),
                        'id_match'       => $this->id_match,
                        'config'         => $config,
                ],
        ];

        $this->enqueueWorker( self::GLOSSARY_WRITE, $params );
    }

    /**
     * Enqueue a Worker
     *
     * @param $queue
     * @param $params
     */
    private function enqueueWorker( $queue, $params ) {
        try {
            WorkerClient::enqueue( $queue, '\AsyncTasks\Workers\GlossaryWorker', $params, [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );
        } catch ( Exception $e ) {
            # Handle the error, logging, ...
            $output = "**** Glossary enqueue request failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $params, true );
            Log::doJsonLog( $output );
            Utils::sendErrMailReport( $output );
        }
    }
}
