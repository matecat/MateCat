<?php

namespace Features\Dqf\Service;


use Features\Dqf;
use Translations\WarningModel;

use Features\Dqf\Service\Struct\ISessionBasedRequestStruct ;

class Client {

    protected $curl_options = array() ;
    /**
     * @var Session
     */
    protected $session;

    public function __construct() {

    }

    public function getSession() {
        return $this->session ;
    }

    public function setCredentials( $username, $password ) {

        if ( is_null( $this->session ) ) {
            $this->session = new Session( $this, $username, $password );
            $this->session->login(); // TODO: defer this to lazy load when sessionId is required
        }

        return $this->session ;
    }

    public function url( $path ) {
        $base = preg_replace('/\/$/', '', \INIT::$DQF_BASE_URL );
        return $base . '/v3' . $path ;
    }

    public function _curlOptionsForPostParams( $params ) {
        $out = [];
        $out[ CURLOPT_POST ]           = true ;
        $out[ CURLOPT_POSTFIELDS ]     = $params ;
        $out[ CURLOPT_RETURNTRANSFER ] = true ;
        return $out ;
    }

    public function setHeaders( ISessionBasedRequestStruct $requestParams ) {
        $out = array();
        $out[ CURLOPT_HTTPHEADER ] = $this->headers( $requestParams->getHeaders() );
        $this->curl_options = $out + $this->curl_options ;
    }

    public function setPostParams( ISessionBasedRequestStruct $requestStruct ) {
        $params = $this->_curlOptionsForPostParams( $requestStruct->getParams() ) ;
        $this->curl_options = $params + $this->curl_options ;
    }

    public function getCurlOptions() {
        return $this->curl_options ;
    }

    /**
     * Returns array of curl options to configure the curl resource.
     *
     * @param       $params
     * @param array $headers
     *
     * @return array
     */
    public function optionsGet( $params = array(), $headers = array() ) {
        $out = array();
        $out[ CURLOPT_HTTPHEADER ] = $this->headers( $headers ) ;
        return $out;
    }

    protected function headers($headers) {
        $out = [];
        foreach( $headers as $key => $header ) {
            $out[] = $key . ': ' . $header ;
        }
        return $out ;
    }

    function encrypt($input) {
         $size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
         $input = $this->pkcs5_pad($input, $size);

         $key = \INIT::$DQF_ENCRYPTION_KEY ;

         $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CBC, '');
         $iv = \INIT::$DQF_ENCRYPTION_IV ;

         mcrypt_generic_init($td, $key, $iv);
         $data = mcrypt_generic($td, $input);
         mcrypt_generic_deinit($td);
         mcrypt_module_close($td);
         $data = base64_encode($data);
         return $data;
     }

    function decrypt( $code ) {
        $code = base64_decode( $code ) ;

        $key = \INIT::$DQF_ENCRYPTION_KEY ;
        $iv = \INIT::$DQF_ENCRYPTION_IV ;

        $td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', 'cbc', $iv);

        mcrypt_generic_init($td, $key, $iv);
        $decrypted = mdecrypt_generic($td, $code);

        $decrypted = $this->pkcs5_unpad( $decrypted ) ;
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);

        return utf8_encode(trim($decrypted));
    }

    function pkcs5_pad ($text, $blocksize) {
         $pad = $blocksize - (strlen($text) % $blocksize);
         return $text . str_repeat(chr($pad), $pad);
     }

     function pkcs5_unpad($text) {
         $pad = ord($text{strlen($text)-1});
         if ($pad > strlen($text)) return false;
         if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) return false;
         return substr($text, 0, -1 * $pad);
     }

}
