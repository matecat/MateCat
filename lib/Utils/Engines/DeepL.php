<?php

use Engines\DeepL\DeepLApiClient;

class Engines_DeepL extends Engines_AbstractEngine {
    private $apiKey;

    public function setApiKey( $apiKey ) {
        $this->apiKey = $apiKey;
    }

    /**
     * @return DeepLApiClient
     * @throws Exception
     */
    protected function _getClient() {
        if ( $this->apiKey === null ) {
            throw new Exception( "API ket not set" );
        }

        return DeepLApiClient::newInstance( $this->apiKey );
    }

    /**
     * @param       $rawValue
     * @param array $parameters
     * @param null $function
     * @return Engines_Results_MT[]
     * @throws Exception
     */
    protected function _decode( $rawValue, array $parameters = [], $function = null ) {
        $rawValue    = json_decode( $rawValue, true );
        $translation = $rawValue[ 'translations' ][ 0 ][ 'text' ];
        $translation = html_entity_decode( $translation, ENT_QUOTES | 16 );
        $source      = $parameters[ 'source_lang' ];
        $target      = $parameters[ 'target_lang' ];
        $segment     = $parameters[ 'text' ][ 0 ];

        return ( new Engines_Results_MyMemory_Matches([
            'source' => $source,
            'target' => $target,
            'raw_segment' => $segment,
            'translation' => $translation,
            'match' => "85%",
            'created-by' => "MT-" . $this->getName(),
            'create-date' => date( "Y-m-d" ),
        ] ) )->getMatches( 1, [], $source, $target );
    }

    /**
     * @inheritDoc
     */
    public function get( $_config ) {

        try {
            $source = explode( "-", $_config[ 'source' ] );
            $target = explode( "-", $_config[ 'target' ] );

            $extraParams = $this->getEngineRecord()->extra_parameters;

            if ( !isset( $extraParams[ 'DeepL-Auth-Key' ] ) ) {
                throw new Exception( "DeepL API key not set" );
            }

            // glossaries (only for DeepL)
            $metadataDao     = new Projects_MetadataDao();
            $deepLFormality  = $metadataDao->get( $_config[ 'pid' ], 'deepl_formality', 86400 );
            $deepLIdGlossary = $metadataDao->get( $_config[ 'pid' ], 'deepl_id_glossary', 86400 );

            if ( $deepLFormality !== null ) {
                $_config[ 'formality' ] = $deepLFormality->value;
            }

            if ( $deepLIdGlossary !== null ) {
                $_config[ 'idGlossary' ] = $deepLIdGlossary->value;
            }
            // glossaries (only for DeepL)

            $parameters = [
                'text' => [
                    $_config['segment'],
                ],
                'source_lang' => $source[0],
                'target_lang' => $target[0],
                'formality' => ($_config['formality'] ?: null),
                'glossary_id' => ($_config['idGlossary'] ?: null)
            ];

            $headers = [
                    'Authorization: DeepL-Auth-Key ' . $extraParams[ 'DeepL-Auth-Key' ],
                    'Content-Type: application/json'
            ];

            $this->_setAdditionalCurlParams(
                    [
                            CURLOPT_POST           => true,
                            CURLOPT_POSTFIELDS     => json_encode( $parameters ),
                            CURLOPT_HTTPHEADER     => $headers,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HEADER         => false,
                            CURLOPT_SSL_VERIFYPEER => true,
                            CURLOPT_SSL_VERIFYHOST => 2
                    ]
            );

            $this->call( "translate_relative_url", $parameters, true );

            return $this->result;

        } catch ( Exception $e ) {
            return $this->GoogleTranslateFallback( $_config );
        }
    }

    /**
     * @inheritDoc
     */
    public function set( $_config ) {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    /**
     * @inheritDoc
     */
    public function update( $_config ) {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    /**
     * @inheritDoc
     */
    public function delete( $_config ) {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    /**
     * @return mixed
     * @throws DeepLApiException
     * @throws Exception
     */
    public function glossaries() {
        return $this->_getClient()->allGlossaries();
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws DeepLApiException
     * @throws Exception
     */
    public function getGlossary( $id ) {
        return $this->_getClient()->getGlossary( $id );
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws DeepLApiException
     * @throws Exception
     */
    public function deleteGlossary( $id ) {
        return $this->_getClient()->deleteGlossary( $id );
    }

    /**
     * @param $data
     *
     * @return mixed
     * @throws DeepLApiException
     */
    public function createGlossary( $data ) {
        return $this->_getClient()->createGlossary( $data );
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws DeepLApiException
     */
    public function getGlossaryEntries( $id ) {
        return $this->_getClient()->getGlossaryEntries( $id );
    }
}
    