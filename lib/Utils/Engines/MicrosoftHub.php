<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/15
 * Time: 12.12
 *
 */

namespace Utils\Engines;

use DOMDocument;
use Exception;
use Model\Engines\Structs\MicrosoftHubStruct;
use Utils\Constants\EngineConstants;
use Utils\Engines\Traits\Oauth;

/**
 * Class MicrosoftHub
 * @property string oauth_url
 * @property string token
 * @property string client_id
 * @property string client_secret
 */
class MicrosoftHub extends AbstractEngine {

    use Oauth;

    private string $rawXmlErrStruct = <<<TAG
            <html lang="en">
                <body>
                    <h1>%s</h1>
                    <p>Method: %s</p>
                    <p>Parameter: </p>
                    <p>Message: %s</p>
                    <code></code>
                </body>
            </html>
TAG;

    protected array $_config = [
            'segment'     => null,
            'translation' => null,
            'source'      => null,
            'target'      => null,
    ];

    protected $_auth_parameters = [
            'client_id'  => "",

        /**
         * Hardcoded params, from documentation
         * @see https://msdn.microsoft.com/en-us/library/hh454950.aspx
         */
            'grant_type' => "client_credentials",
            'scope'      => "https://api.microsofttranslator.com"
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

        if ( $lang == 'zh-CN' ) {
            return "zh-CHS";
        } //chinese zh-CHS simplified
        if ( $lang == 'zh-TW' ) {
            return "zh-CHT";
        } //chinese zh-CHT traditional
        $l = explode( "-", strtolower( trim( $lang ) ) );

        return $l[ 0 ];

    }


    protected function getAuthParameters(): array {
        return [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => "",
                CURLOPT_HTTPHEADER => [
                        'Ocp-Apim-Subscription-Key: ' . $this->client_id, //key1
                        'Accept: application/jwt',
                        'Content-Type: application/json',
                ]
        ];
    }

    /**
     * @param       $rawValue
     * @param array $parameters
     * @param null  $function
     *
     * @return array
     * @throws Exception
     */
    protected function _decode( $rawValue, array $parameters = [], $function = null ): array {

        $all_args = func_get_args();

        if ( !empty( $rawValue[ 'error' ] ) ) {
            $xmlObj = simplexml_load_string( $rawValue[ 'error' ][ 'response' ], 'SimpleXMLElement', LIBXML_NOENT );
            if ( empty( $xmlObj ) ) {
                $jsonObj = json_decode( $rawValue[ 'error' ][ 'response' ] );
                $decoded = [
                        'error' => [ "message" => $jsonObj->message, 'code' => -1 ]
                ];
            } else {
                $decoded = [
                        'error' => [ "message" => $xmlObj->body->h1 . ": " . $xmlObj->body->p[ 2 ], 'code' => -1 ]
                ];
            }

            return $decoded;
        }

        $decoded = [];

        $xmlObj = simplexml_load_string( $rawValue, 'SimpleXMLElement', LIBXML_NOENT | LIBXML_NOEMPTYTAG );

        foreach ( (array)$xmlObj[ 0 ] as $val ) {

            /*$decoded = [
                    'data' => [
                            "translations" => [
                                    [ 'translatedText' => $this->_resetSpecialStrings( html_entity_decode( $val, ENT_QUOTES | 16  ) ) ]
                            ]
                    ]
            ];*/

            $val = preg_replace( '|(<[^>]+>) (<[^>]+>)|', '${1}${2}', $val );

            $dDoc = new DOMDocument();
            @$dDoc->loadXML( "<root>$val</root>", LIBXML_NOENT | LIBXML_NOEMPTYTAG );
            $tagList = $dDoc->getElementsByTagName( "root" );
            $tmpTag  = "";

            foreach ( $tagList as $_tag ) {
                foreach ( $_tag->childNodes as $node ) {
                    $tmpTag .= $dDoc->saveXML( $node );
                }
            }

            $decoded = [
                    'data' => [
                            "translations" => [
                                    [ 'translatedText' => html_entity_decode( $tmpTag, ENT_QUOTES | 16 ) ]
                            ]
                    ]
            ];


        }

        return $this->_composeMTResponseAsMatch( $all_args[ 1 ][ 'text' ], $decoded );

    }


    public function set( $_config ): bool {
        // Microsoft Hub does not have this method
        return true;
    }

    public function update( $_config ): bool {
        // Microsoft Hub does not have this method
        return true;
    }

    public function delete( $_config ): bool {
        // Microsoft Hub does not have this method
        return true;
    }

    protected function _formatAuthenticateError( array $objResponse ): string {

        //format as a normal Translate Response and send to decoder to output the data
        return sprintf( $this->rawXmlErrStruct, $objResponse[ 'error' ], 'getToken', $objResponse[ 'error_description' ] );

    }

    protected function _getEngineStruct(): MicrosoftHubStruct {

        return MicrosoftHubStruct::getStruct();

    }

    protected function _setTokenEndLife( ?int $expires_in_seconds = null ) {

        /**
         * Gain a minute to not fallback into a recursion
         *
         * @see static::get
         */
        $this->token_endlife = time() + $expires_in_seconds - 60;

    }

    protected function _checkAuthFailure(): bool {
        return ( stripos( $this->result[ 'error' ][ 'message' ] ?? '', 'token has expired' ) !== false );
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
        $parameters = [];

        $parameters[ 'appId' ]    = 'Bearer ' . $this->token;
        $parameters[ 'to' ]       = $this->_fixLangCode( $_config[ 'target' ] );
        $parameters[ 'from' ]     = $this->_fixLangCode( $_config[ 'source' ] );
        $parameters[ 'text' ]     = $_config[ 'segment' ];
        $parameters[ 'category' ] = $this->getEngineRecord()->extra_parameters[ 'category' ];

        return $parameters;

    }

    /**
     * @inheritDoc
     */
    public function getExtraParams(): array {
        return [
                'enable_mt_analysis',
        ];
    }
}