<?php

namespace Utils\Engines;

use Exception;
use Model\Engines\SmartMATEStruct;
use Utils\Constants\EngineConstants;
use Utils\Engines\Traits\Oauth;

/**
 * Created by PhpStorm.
 * @property string client_secret
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/03/15
 * Time: 12.10
 *
 */
class SmartMATE extends AbstractEngine {

    use Oauth;

    protected $_auth_parameters = [
            'client_id'     => null,
            'client_secret' => null,

        /**
         * Hardcoded params, from documentation
         * @see https://mt.smartmate.co/translate
         */
            'grant_type'    => "client_credentials",
            'scope'         => "translate"
    ];

    protected array $_config = [
            'segment'     => null,
            'translation' => null,
            'source'      => null,
            'target'      => null,
    ];

    /**
     * @throws Exception
     */
    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );
        if ( $this->getEngineRecord()->type != EngineConstants::MT ) {
            throw new Exception( "Engine {$this->getEngineRecord()->id} is not a MT engine, found {$this->getEngineRecord()->type} -> {$this->getEngineRecord()->class_load}" );
        }
    }

    protected function _fixLangCode( $lang ) {
        $l = explode( "-", strtolower( trim( $lang ) ) );

        return $l[ 0 ];
    }

    protected function _formatAuthenticateError( $objResponse ) {

        //format as a normal Translate Response and send to decoder to output the data
        return $objResponse;

    }

    /**
     * @throws Exception
     */
    protected function _decode( $rawValue, array $parameters = [], $function = null ) {

        $all_args = func_get_args();

        if ( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
            $decoded = [
                    'data' => [
                            "translations" => [
                                    [ 'translatedText' => $decoded[ "translation" ] ]
                            ]
                    ]
            ];
        } else {

            if ( $rawValue[ 'error' ][ 'code' ] == 0 && $rawValue[ 'responseStatus' ] >= 400 ) {
                $rawValue[ 'error' ][ 'code' ] = -$rawValue[ 'responseStatus' ];
            }

            $decoded = $rawValue; // already decoded in case of error
        }

        return $this->_composeMTResponseAsMatch( $all_args[ 1 ][ 'text' ], $decoded );

    }

    protected function _getEngineStruct(): SmartMATEStruct {

        return SmartMATEStruct::getStruct();

    }

    protected function _setTokenEndLife( ?int $expires_in_seconds = null ) {

        if ( !is_null( $expires_in_seconds ) ) {
            $this->token_endlife = $expires_in_seconds;

            return;
        }

        /**
         * Gain 2 minutes to not fallback into a recursion
         *
         * @see static::get
         */
        $this->token_endlife = time() + 3480;

    }

    protected function _checkAuthFailure(): int {
        $expiration   = ( stripos( $this->result[ 'error' ][ 'message' ] ?? '', 'token is expired' ) !== false );
        $auth_failure = $this->result[ 'error' ][ 'code' ] < 0;

        return $expiration | $auth_failure;
    }


    public function set( $_config ): bool {
        // SmartMATE does not have this method
        return true;
    }

    public function update( $_config ): bool {
        // SmartMATE does not have this method
        return true;
    }

    public function delete( $_config ): bool {
        // SmartMATE does not have this method
        return true;
    }

    /**
     * @throws Exception
     */
    protected function _formatRecursionError(): array {
        return $this->_composeMTResponseAsMatch(
                '',
                [
                        'error'          => [
                                'code'     => -499,
                                'message'  => "Client Closed Get",
                                'response' => 'Maximum recursion limit reached'
                            // Some useful info might still be contained in the response body
                        ],
                        'responseStatus' => 499
                ] //return negative number
        );
    }

    protected function _fillCallParameters( array $_config ): array {
        $parameters           = [];
        $parameters[ 'text' ] = $_config[ 'segment' ];
        $parameters[ 'from' ] = $_config[ 'source' ];
        $parameters[ 'to' ]   = $_config[ 'target' ];

        return $parameters;
    }


}