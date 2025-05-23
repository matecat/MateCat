<?php

namespace AbstractControllers;

use API\Commons\Validators\Base;
use ApiKeys_ApiKeyStruct;
use Bootstrap;
use CatUtils;
use Controller\Authentication\AuthenticationHelper;
use Controller\Authentication\AuthenticationTrait;
use Exception;
use FeatureSet;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;
use Log;
use ReflectionException;
use Traits\TimeLogger;

abstract class KleinController implements IController {

    use TimeLogger;
    use AuthenticationTrait;

    protected bool $useSession = false;

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * @var Response
     */
    protected Response         $response;
    protected ?ServiceProvider $service = null;
    protected ?App             $app     = null;

    /**
     * @var Base[]
     */
    protected array $validators = [];

    /**
     * @var ApiKeys_ApiKeyStruct|null
     */
    protected ?ApiKeys_ApiKeyStruct $api_record = null;

    /**
     * @var array
     */
    public array $params = [];

    /**
     * @var ?FeatureSet
     */
    protected ?FeatureSet $featureSet = null;

    /**
     * @return FeatureSet
     */
    public function getFeatureSet(): FeatureSet {
        return $this->featureSet;
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return $this
     */
    public function setFeatureSet( FeatureSet $featureSet ): KleinController {
        $this->featureSet = $featureSet;

        return $this;
    }

    /**
     * @return array
     */
    public function getParams(): array {
        return $this->params;
    }

    /**
     * @param Request          $request
     * @param Response         $response
     * @param ?ServiceProvider $service
     * @param ?App             $app
     *
     * @throws Exception
     */
    public function __construct( Request $request, Response $response, ?ServiceProvider $service = null, ?App $app = null ) {

        $this->startTimer();
        $this->timingLogFileName = 'api_calls_time.log';

        $this->request  = $request;
        $this->response = $response;
        $this->service  = $service;
        $this->app      = $app;

        $paramsPut        = $this->getPutParams() ?: [];
        $paramsGet        = $this->request->paramsNamed()->getIterator()->getArrayCopy();
        $this->params     = $this->request->paramsPost()->getIterator()->getArrayCopy();
        $this->params     = array_merge( $this->params, $paramsGet, $paramsPut );
        $this->featureSet = new FeatureSet();
        $this->identifyUser( $this->useSession );
        $this->afterConstruct();

    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function refreshClientSessionIfNotApi() {
        if ( empty( $this->api_key ) ) {
            Bootstrap::sessionStart();
            AuthenticationHelper::refreshSession( $_SESSION );
        }
    }

    /**
     * @throws Exception
     */
    public function performValidations() {
        $this->validateRequest();
    }

    /**
     * @param $method
     *
     * @throws Exception
     */
    public function respond( $method ) {

        $this->performValidations();

        if ( !$this->response->isLocked() ) {
            $this->$method();
        }

        $this->_logWithTime();

    }

    public function getRequest(): Request {
        return $this->request;
    }

    public function getPutParams() {
        return json_decode( file_get_contents( 'php://input' ), true );
    }

    /**
     * @throws Exception
     */
    protected function validateRequest() {
        foreach ( $this->validators as $validator ) {
            $validator->validate();
        }
        $this->validators = [];
        $this->afterValidate();
    }

    protected function appendValidator( Base $validator ): KleinController {
        $this->validators[] = $validator;

        return $this;
    }

    protected function afterConstruct() {
    }

    protected function _logWithTime() {
        $this->logPageCall();
    }

    protected function afterValidate() {

    }

    /**
     * @return false|int
     */
    protected function isJsonRequest() {
        return preg_match( '~^application/json~', $this->request->headers()->get( 'Content-Type' ) );
    }

    /**
     * @return bool|null
     */
    protected function isRevision(): ?bool {
        $controller = $this;

        if ( isset( $controller->id_job ) and isset( $controller->received_password ) ) {
            $jid        = $controller->id_job;
            $password   = $controller->received_password;
            $isRevision = CatUtils::isRevisionFromIdJobAndPassword( $jid, $password );

            if ( !$isRevision ) {
                $isRevision = CatUtils::getIsRevisionFromReferer();
            }

            return $isRevision;
        }

        return CatUtils::getIsRevisionFromReferer();
    }


    /**
     * @param $id_segment
     *
     * @return array
     */
    protected function parseIdSegment( $id_segment ): array {
        $parsedSegment = explode( "-", $id_segment );

        return [
                'id_segment' => $parsedSegment[ 0 ],
                'split_num'  => $parsedSegment[ 1 ] ?? null,
        ];
    }

    /**
     * @param      $message
     * @param null $filename
     */
    protected function log( $message, $filename = null ): void {
        Log::doJsonLog( $message, $filename );
    }
}
