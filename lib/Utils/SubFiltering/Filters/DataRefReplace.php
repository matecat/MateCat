<?php

namespace SubFiltering\Filters;

use Matecat\XliffParser\XliffUtils\DataRefReplacer;
use SubFiltering\Commons\AbstractHandler;

class DataRefReplace extends AbstractHandler {

    /**
     * @var array
     */
    private $dataRefMap;

    /**
     * DataRefReplace constructor.
     *
     * @param array $dataRefMap
     */
    public function __construct( array $dataRefMap = [] ) {
        parent::__construct();
        $this->dataRefMap = $dataRefMap;
    }

    /**
     * @inheritDoc
     */
    public function transform( $segment ) {

        // dataRefMap is present only in xliff 2.0 files
        if ( empty( $this->dataRefMap ) ) {
            return $segment;
        }

        $dataRefReplacer = new DataRefReplacer( $this->dataRefMap );

        //
        // ************************************************************
        // NOTES 2021-04-02
        // ************************************************************
        //
        // Added support for <pc> tags.
        // At this point <pc> tags are incapsulated into a Matecat <ph> generic tag as:
        //
        // Link semplice: &lt;ph id="mtc_1" equiv-text="base64:Jmx0O3BjIGlkPSIxIiBjYW5Db3B5PSJubyIgY2FuRGVsZXRlPSJubyIgZGF0YVJlZkVuZD0iZDIiIGRhdGFSZWZTdGFydD0iZDEiJmd0Ow=="/&gt;La Repubblica&lt;ph id="mtc_2" equiv-text="base64:Jmx0Oy9wYyZndDs="/&gt;.
        //
        // We need to turn back to orignal <pc> tags in order to use $dataRefReplacer->replace function.
        // Pay attention, only <pc> tags with a correspondence on dataRef map should be converted
        //
        $parsed = \Matecat\XliffParser\Utils\HtmlParser::parse( $segment );
        $closingPcMap = [];

        foreach ( $parsed as $element ) {

            // if $element is a matecat <ph>
            if ( $element->tagname === 'ph' and isset( $element->attributes[ 'equiv-text' ] ) ) {
                $value = base64_decode( str_replace( 'base64:', '', $element->attributes[ 'equiv-text' ] ) );

                if ( !$this->isAnEncodedClosingPcTag( $value ) ) {
                    if ( $this->isAnEncodedOpeningPcTagWithCorrespondingDataRef( $value ) ) {
                        $segment = str_replace( $element->node, $value, $segment );
                        $closingPcMap[]  = true;
                    } else {
                        $closingPcMap[] = false;
                    }
                } else {
                    if ( end( $closingPcMap ) === true ) {
                        $segment = str_replace( $element->node, $value, $segment );
                    }

                    array_pop( $closingPcMap );
                }
            }
        }

        return $dataRefReplacer->replace( $segment );
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    private function isAnEncodedClosingPcTag( $string ) {
        return $string === '&lt;/pc&gt;';
    }

    /**
     * This function check if $string is an encoded opening <pc> tag which has a corresponding value to replace on dataRef map
     *
     * @param string $string
     *
     * @return bool
     */
    private function isAnEncodedOpeningPcTagWithCorrespondingDataRef( $string ) {

        // [2] => dataRefStart
        // [3] => dataRefEnd
        // [4] => dataRef
        preg_match_all( '/(dataRefStart=\"(.*?)\"|dataRefEnd=\"(.*?)\"|dataRef=\"(.*?)\")/iu', $string, $matches );

        $dataRefId = null;

        if ( isset( $matches[ 2 ][ 0 ] ) and "" !== $matches[ 2 ][ 0 ] ) {
            $dataRefId = $matches[ 2 ][ 0 ];
        } elseif ( isset( $matches[ 3 ][ 0 ] ) and "" !== $matches[ 3 ][ 0 ] ) {
            $dataRefId = $matches[ 3 ][ 0 ];
        } elseif ( isset( $matches[ 4 ][ 0 ] ) and "" !== $matches[ 4 ][ 0 ] ) {
            $dataRefId = $matches[ 4 ][ 0 ];
        }

        // if there is no correspondence return false
        if ( null === $dataRefId or !key_exists( $dataRefId, $this->dataRefMap ) ) {
            return false;
        }

        // check if is an encoded pc tag
        return strpos( $string, '&lt;pc' ) !== false;
    }

}