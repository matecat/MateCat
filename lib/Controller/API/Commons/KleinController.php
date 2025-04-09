<?php

namespace API\Commons;

use AbstractControllers\IController;
use AbstractControllers\TimeLogger;
use API\Commons\Authentication\AuthenticationHelper;
use API\Commons\Authentication\AuthenticationTrait;
use API\Commons\Exceptions\AuthenticationError;
use API\Commons\Validators\Base;
use ApiKeys_ApiKeyStruct;
use Bootstrap;
use CatUtils;
use DomainException;
use Exception;
use Exceptions\NotFoundException;
use FeatureSet;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Log;
use ReflectionException;
use RuntimeException;
use SebastianBergmann\Invoker\TimeoutException;
use Swaggest\JsonSchema\InvalidValue;
use Validator\Errors\JSONValidatorException;
use Validator\Errors\JsonValidatorGenericException;

/**
 * @property  int    revision_number
 * @property  string password
 * @property  int    id_job
 */
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
    protected Response $response;
    protected          $service;
    protected          $app;

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
     * @return mixed
     */
    public function getParams() {
        return $this->params;
    }

    /**
     * KleinController constructor.
     *
     * @param $request
     * @param $response
     * @param $service
     * @param $app
     *
     * @throws ReflectionException
     */
    public function __construct( $request, $response, $service, $app ) {

        $this->startTimer();
        $this->timingLogFileName = 'api_calls_time.log';

        $this->request  = $request;
        $this->response = $response;
        $this->service  = $service;
        $this->app      = $app;

        $paramsPut        = $this->getPutParams();
        $paramsGet        = $this->request->paramsNamed()->getIterator()->getArrayCopy();
        $this->params     = $this->request->paramsPost()->getIterator()->getArrayCopy();
        $this->params     = array_merge( $this->params, $paramsGet, ( empty( $paramsPut ) ? [] : $paramsPut ) );
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

    public function getRequest() {
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

    protected function appendValidator( Base $validator ) {
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
     * @param $id_job
     * @param $password
     * @return bool|null
     */
    protected function isRevision($id_job, $password): ?bool
    {
        $isRevision = CatUtils::getIsRevisionFromIdJobAndPassword( $id_job, $password );

        if ( null === $isRevision ) {
            $isRevision = CatUtils::getIsRevisionFromReferer();
        }

        return $isRevision;
    }

    /**
     * @param Exception $exception
     * @return Response
     */
    protected function returnException(Exception $exception): Response
    {
        // determine http code
        switch (get_class($exception)){

            case InvalidValue::class:
            case JSONValidatorException::class:
            case JsonValidatorGenericException::class:
            case InvalidArgumentException::class:
            case DomainException::class:
                $httpCode = 400;
                break;

            case AuthenticationError::class:
                $httpCode = 401;
                break;

            case NotFoundException::class:
                $httpCode = 404;
                break;

            case RuntimeException::class:
                $httpCode = 500;
                break;

            case TimeoutException::class:
                $httpCode = 504;
                break;

            default:
                $httpCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
                break;
        }

        $this->response->code($httpCode);

        return $this->response->json([
            'errors' => [
                "code" => $exception->getCode(),
                "message" => $exception->getMessage(),
                "debug" => [
                    "trace" => $exception->getTrace(),
                    "file" => $exception->getFile(),
                    "line" => $exception->getLine(),
                ]
            ]
        ]);
    }

    /**
     * @param $id_segment
     * @return array
     */
    protected function parseIdSegment($id_segment): array
    {
        $parsedSegment = explode( "-", $id_segment );

        return [
            'id_segment' => $parsedSegment[0],
            'split_num' => $parsedSegment[1],
        ];
    }

    /**
     * @param $message
     * @param null $filename
     */
    protected function log($message, $filename = null ): void
    {
        Log::doJsonLog( $message, $filename );
    }
}
