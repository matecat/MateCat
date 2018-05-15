<?php

namespace API\V2;

use AbstractControllers\IController;
use API\V2\Exceptions\AuthenticationError;
use API\V2\Validators\Base;
use ApiKeys_ApiKeyStruct;
use AuthCookie;
use FeatureSet;
use Users_UserDao;

abstract class KleinController implements IController {

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

    /**
     * @return mixed
     */
    public function getParams() {
        return $this->params;
    }

    public function __construct( $request, $response, $service, $app ) {
        $this->request  = $request;
        $this->response = $response;
        $this->service  = $service;
        $this->app      = $app;

        $paramsPut = $this->getPutParams();
        $paramsGet = $this->request->paramsNamed()->getIterator()->getArrayCopy();
        $this->params = $this->request->paramsPost()->getIterator()->getArrayCopy();
        $this->params = array_merge( $this->params, $paramsGet, ( empty( $paramsPut ) ? [] : $paramsPut ) );
        $this->featureSet = new FeatureSet();
        $this->validateAuth();
        $this->afterConstruct();

    }

    public function performValidations(){
        //$this->validateAuth();
        $this->identifyUser();
        $this->validateRequest();
    }

    public function respond( $method ) {
        $start = microtime(true) ;

        $this->performValidations();

        if ( !$this->response->isLocked() ) {
            $this->$method();
        }

        $end = microtime(true) ;

        $this->_logWithTime( $end - $start ) ;

    }

    public function getRequest() {
        return $this->request  ;
    }

    protected function validateAuth() {
        $headers = $this->request->headers();

        $this->api_key    = $headers[ 'x-matecat-key' ];
        $this->api_secret = $headers[ 'x-matecat-secret' ];

        if ( FALSE !== strpos( $this->api_key, '-' ) ) {
            list( $this->api_key, $this->api_secret ) = explode('-', $this->api_key ) ;
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

            $dao = new Users_UserDao();
            $dao->setCacheTTL( 3600 );
            $this->user = $dao->getByUid( $user_credentials[ 'uid' ] ) ;
        }

        return $this->user;
    }

    public function getUser(){
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

    protected function validateRequest() {
        foreach( $this->validators as $validator ){
            $validator->validate();
        }
        $this->validators = [];
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

        setcookie(
                $this->downloadToken,
                $cookieContent,
                2147483647,            // expires January 1, 2038
                "/",
                $_SERVER[ 'HTTP_HOST' ]
        );

        $this->downloadToken = null;
    }

    protected function afterConstruct() {}

    protected function _logWithTime( $time ) {
        $previous_filename = \Log::$fileName ;
        \Log::$fileName = 'API.log' ;

        $log_string = " " . $this->request->method() . " " . $this->request->pathname() . " " . round( $time, 4 ) ;

        if ( $this->api_key ) {
            $log_string .= " key:" . $this->api_key ;
        }

        \Log::doLog( $log_string . " " . json_encode( $this->params ) );
        \Log::$fileName = $previous_filename ;
    }

}
