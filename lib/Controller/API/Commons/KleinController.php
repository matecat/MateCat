<?php

namespace API\Commons;

use AbstractControllers\IController;
use AbstractControllers\TimeLogger;
use API\Commons\Exceptions\AuthenticationError;
use API\Commons\Validators\Base;
use ApiKeys_ApiKeyStruct;
use AuthCookie;
use CatUtils;
use CookieManager;
use DomainException;
use Exception;
use Exceptions\NotFoundException;
use FeatureSet;
use INIT;
use InvalidArgumentException;
use Klein\Response;
use RuntimeException;
use Swaggest\JsonSchema\InvalidValue;
use Users_UserDao;
use Validator\Errors\JSONValidatorException;
use Validator\Errors\JsonValidatorGenericException;

/**
 * @property  int revision_number
 * @property  string password
 * @property  int id_job
 */
abstract class KleinController implements IController {

    use TimeLogger;

    /**
     * @var \Klein\Request
     */
    protected $request;

    /**
     * @var \Klein\Response
     */
    protected $response;
    protected $service;
    protected $app;

    protected $downloadToken;

    protected $api_key;
    protected $api_secret;

    /**
     * @var Base[]
     */
    protected $validators = [];

    /**
     * @var \Users_UserStruct
     */
    protected $user;

    /**
     * @var ApiKeys_ApiKeyStruct
     */
    protected $api_record;

    /**
     * @var array
     */
    public $params;

    /**
     * @var FeatureSet
     */
    protected $featureSet;

    /**
     * @var bool
     */
    protected $userIsLogged = false;

    /**
     * @return FeatureSet
     */
    public function getFeatureSet() {
        return $this->featureSet;
    }

    /**
     * @param FeatureSet $featuresSet
     *
     * @return $this
     */
    public function setFeatureSet( FeatureSet $featuresSet ) {
        $this->featureSet = $featuresSet;

        return $this;
    }

    public function getUser(){
        return $this->user;
    }

    public function userIsLogged(){
        return $this->userIsLogged;
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
     * @throws AuthenticationError
     */
    public function __construct( $request, $response, $service, $app ) {

        $this->startTimer();
        $this->timingLogFileName  = 'api_calls_time.log';

        $this->request  = $request;
        $this->response = $response;
        $this->service  = $service;
        $this->app      = $app;

        $paramsPut = $this->getPutParams();
        $paramsGet = $this->request->paramsNamed()->getIterator()->getArrayCopy();
        $this->params = $this->request->paramsPost()->getIterator()->getArrayCopy();
        $this->params = array_merge( $this->params, $paramsGet, ( empty( $paramsPut ) ? [] : $paramsPut ) );
        $this->featureSet = new FeatureSet();
        $this->authenticate();
    }

    /**
     * @throws AuthenticationError
     */
    public function authenticate(){
        $this->validateAuth();
        $this->identifyUser();
        $this->afterConstruct();
    }

    /**
     * @throws Exception
     */
    public function performValidations(){
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

        $this->_logWithTime() ;

    }

    public function getRequest() {
        return $this->request  ;
    }

    protected function validateAuth() {
        $headers = $this->request->headers();

        $this->api_key    = $headers[ 'x-matecat-key' ];
        $this->api_secret = $headers[ 'x-matecat-secret' ];

        if ( FALSE !== strpos( $this->api_key, '-' ) ) {
            [ $this->api_key, $this->api_secret ] = explode('-', $this->api_key ) ;
        }

        if ( !$this->validKeys() ) {
            throw new AuthenticationError( "Invalid Login.", 401 );
        }

    }

    public function getPutParams() {
        return json_decode( file_get_contents( 'php://input' ), true ) ;
    }

    /**
     * @return \Users_UserStruct
     */
    protected function identifyUser(){

        if( !empty( $this->api_record ) ){
            $this->user = $this->api_record->getUser();
        } else { //check if there is an opened cookie

            $user_credentials = [];
            if( isset( $_SESSION[ 'uid' ] ) ){
                $user_credentials[ 'uid' ] = $_SESSION[ 'uid' ];
            } else {
                $user_credentials = AuthCookie::getCredentials(); //validated cookie
            }

            if( !empty( $user_credentials ) && !empty( $user_credentials[ 'uid' ] ) ){
                $dao = new Users_UserDao();
                $dao->setCacheTTL( 3600 );
                $this->user = $dao->getByUid( $user_credentials[ 'uid' ] ) ;
            }

        }

        if( !empty( $this->user ) ){
            $this->userIsLogged = true;
        }

        return $this->user;
    }

    /**
     * validKeys
     *
     * This was implemented to allow to pass a pair of keys just to identify the user, not to deny access.
     * This function returns true even if keys are not provided.
     *
     * If keys are provided, it checks for them to be valid.
     *
     */
    protected function validKeys() {

        if ( $this->api_key && $this->api_secret ) {
            $this->api_record = \ApiKeys_ApiKeyDao::findByKey( $this->api_key );

            return $this->api_record &&
                $this->api_record->validSecret( $this->api_secret );
        }

        return true;
    }

    /**
     * @throws Exception
     */
    protected function validateRequest() {
        foreach( $this->validators as $validator ){
            $validator->validate();
        }
        $this->validators = [];
        $this->afterValidate();
    }

    protected function appendValidator( Base $validator ){
        $this->validators[] = $validator;
        return $this;
    }

    /**
     *
     * @param null $tokenContent
     */
    protected function unlockDownloadToken( $tokenContent = null ) {
        if ( !isset( $this->downloadToken ) || empty( $this->downloadToken ) ) {
            return;
        }

        if ( empty( $tokenContent ) ) {
            $cookieContent = json_encode( array(
                    "code"    => 0,
                    "message" => "Download complete."
            ) );
        } else {
            $cookieContent = $tokenContent;
        }

        CookieManager::setCookie( $this->downloadToken,
                $cookieContent,
                [
                        'expires'  => time() + 3600,            // expires in 1 hour
                        'path'     => '/',
                        'domain'   => INIT::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'None',
                ]
        );

        $this->downloadToken = null;
    }

    protected function afterConstruct() {}

    protected function _logWithTime() {

        $log_object = [ "method" => $this->request->method(), "pathname" => $this->request->pathname() ];

        if ( $this->api_key ) {
            $log_object[ "key" ] = $this->api_key;
        }

        $this->logPageCall();

    }

    protected function afterValidate() {

    }

    /**
     * @return false|int
     */
    protected function isJsonRequest()
    {
        return preg_match( '~^application/json~', $this->request->headers()->get( 'Content-Type' ) ) ;
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

            default:
                $httpCode = $exception->getCode() >= 400 ? $exception->getCode() : 500;
                break;
        }

        $this->response->code($httpCode);

        return $this->response->json([
            'errors' => [
                "code" => $exception->getCode(),
                "message" => $exception->getMessage()
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
}
