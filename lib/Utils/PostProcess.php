<?php

use LQA\QA;

class PostProcess extends QA {


    /**
     * Perform all integrity check and comparisons on source and target string
     * 
     */
    public function realignMTSpaces() {

        try {
            list( $srcNodeList, $trgNodeList ) = $this->_prepareDOMStructures();
        } catch ( DOMException $ex ) {
            return $this->getErrors();
        }

        $this->_checkTagMismatch( $srcNodeList, $trgNodeList );

        if( $this->thereAreErrors() ){
            $this->_getTagDiff();
            return; //fail
        }

        list ( $source_seg, $target_seg ) = $this->_realignTMSpaces();

        //- re-import in the dom target after regular expression
        //- perform check again ( recursive over the entire class )
        $qaCheck = new self( $this->source_seg, $target_seg );
        $qaCheck->setFeatureSet( $this->featureSet );
        $qaCheck->performTagCheckOnly();
        if ( !$qaCheck->thereAreErrors() ) {
            $this->target_seg = $target_seg;
            $this->trgDom     = $this->_loadDom( $target_seg, self::ERR_TARGET );
            $this->_resetDOMMaps();
            $this->_prepareDOMStructures();
            //ALL RIGHT
        } else {

            $this->addError( self::ERR_TAG_MISMATCH );

        }

    }

    protected function _realignTMSpaces(){

        $source_seg = preg_split( "/>/", $this->source_seg );
        $target_seg = preg_split( "/>/", $this->target_seg );

        foreach( $source_seg as $pos => $_str  ){

            if( $_str == "" ) continue;
            $target_seg[$pos] = $this->_normalizeHeadSpaces( $_str, $target_seg[$pos] );

        }

        $source_seg = implode( ">", $source_seg );
        $target_seg = implode( ">", $target_seg ) ;

        //RESET another cycle

        $source_seg = preg_split( "/</", $source_seg );
        $target_seg = preg_split( "/</", $target_seg );

        foreach( $source_seg as $pos => $_str  ){

            if( $_str == "" ) continue;
            $target_seg[$pos] = $this->_normalizeTailSpaces( $_str, $target_seg[$pos] );

        }

        $source_seg = ( implode( "<", $source_seg ) );
        $target_seg = ( implode( "<", $target_seg ) );

        return array( $source_seg, $target_seg );

    }

    protected function _normalizeHeadSpaces( $srcNodeContent, $trgNodeContent ){

        $_srcNodeContent = $srcNodeContent;
        $_trgNodeContent = $trgNodeContent; //not Used

        $srcHasHeadNBSP = $this->_hasHeadNBSP( $srcNodeContent );
        $trgHasHeadNBSP = $this->_hasHeadNBSP( $trgNodeContent );

        //normalize spaces and check presence
        $srcNodeContent = $this->_nbspToSpace( $srcNodeContent );
        $trgNodeContent = $this->_nbspToSpace( $trgNodeContent );

        $headSrcWhiteSpaces = mb_stripos( $srcNodeContent, " ", 0, 'utf-8' );
        $headTrgWhiteSpaces = mb_stripos( $trgNodeContent, " ", 0, 'utf-8' );

        //normalize the target first space according to the source type
        if ( $srcHasHeadNBSP != $trgHasHeadNBSP && $srcHasHeadNBSP ) {
            $_trgNodeContent = preg_replace( '/^\x{20}{1}/u', CatUtils::unicode2chr( 0Xa0 ), $_trgNodeContent );
        } elseif ( $srcHasHeadNBSP != $trgHasHeadNBSP && $trgHasHeadNBSP ) {
            $_trgNodeContent = preg_replace( '/^\x{a0}{1}/u', CatUtils::unicode2chr( 0X20 ), $_trgNodeContent );
        }

        if ( ( $headSrcWhiteSpaces === 0 ) && $headSrcWhiteSpaces !== $headTrgWhiteSpaces ) {
            $_trgNodeContent = " " . $_trgNodeContent;
        } elseif ( ( $headSrcWhiteSpaces !== 0 && $headTrgWhiteSpaces === 0 ) && $headSrcWhiteSpaces !== $headTrgWhiteSpaces ){
            $_trgNodeContent = mb_substr( $_trgNodeContent, 1, mb_strlen($_trgNodeContent) );
        }

        return $_trgNodeContent;

    }

    protected function _normalizeTailSpaces( $srcNodeContent, $trgNodeContent ){

        //backup and check start string
        $_srcNodeContent = $srcNodeContent;
        $_trgNodeContent = $trgNodeContent; //not used

        $srcHasTailNBSP = $this->_hasTailNBSP($srcNodeContent);
        $trgHasTailNBSP = $this->_hasTailNBSP($trgNodeContent);

        //normalize spaces
        $srcNodeContent = $this->_nbspToSpace($srcNodeContent);
        $trgNodeContent = $this->_nbspToSpace($trgNodeContent);

        $srcLen = mb_strlen($srcNodeContent);
        $trgLen = mb_strlen($trgNodeContent);

        $trailingSrcChar = mb_substr($srcNodeContent, $srcLen - 1, 1, 'utf-8');
        $trailingTrgChar = mb_substr($trgNodeContent, $trgLen - 1, 1, 'utf-8');

        //normalize the target first space according to the source type
        if ( $srcHasTailNBSP != $trgHasTailNBSP && $srcHasTailNBSP ) {
            $_trgNodeContent = preg_replace( '/\x{20}{1}$/u', CatUtils::unicode2chr( 0Xa0 ), $_trgNodeContent );
        } elseif ( $srcHasTailNBSP != $trgHasTailNBSP && $trgHasTailNBSP ) {
            $_trgNodeContent = preg_replace( '/\x{a0}{1}$/u', CatUtils::unicode2chr( 0X20 ), $_trgNodeContent );
        }

        if ( $trailingSrcChar == " " && $trailingSrcChar != $trailingTrgChar ) {
            $_trgNodeContent = $_trgNodeContent . " ";
        } else if( ( $trailingSrcChar != " " && $trailingTrgChar == " " ) && $trailingSrcChar != $trailingTrgChar  ){
            $_trgNodeContent = mb_substr( $_trgNodeContent, 0, $trgLen - 1 );
        }

        return $_trgNodeContent;

    }

}
