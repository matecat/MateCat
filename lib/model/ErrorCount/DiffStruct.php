<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 28/01/15
 * Time: 14.44
 */
class ErrorCount_DiffStruct extends ErrorCount_Struct {

    /**
     * @var Revise_ReviseStruct
     */
    protected $oldRevision;

    /**
     * @var Revise_ReviseStruct
     */
    protected $newRevision;

    public function __construct( Revise_ReviseStruct $oldRevision = null, Revise_ReviseStruct $newRevision = null ) {
        $this->oldRevision = $oldRevision;
        $this->newRevision = $newRevision;

        $diff = $this->diffRevisions($oldRevision, $newRevision);
        $this->setTyping($diff['err_typing']);
        $this->setTranslation($diff['err_translation']);
        $this->setTerminology($diff['err_terminology']);
        $this->setQuality($diff['err_quality']);
        $this->setStyle($diff['err_style']);
    }

    private function diffRevisions( Revise_ReviseStruct $rev1, Revise_ReviseStruct $rev2 ) {
        if ( empty( $rev1 ) ) {
            $rev1                  = new Revise_ReviseStruct();
            $rev1->err_quality     = Constants_Revise::NONE;
            $rev1->err_style       = Constants_Revise::NONE;
            $rev1->err_terminology = Constants_Revise::NONE;
            $rev1->err_typing      = Constants_Revise::NONE;
            $rev1->err_translation = Constants_Revise::NONE;
        }

        if ( empty( $rev2 ) ) {
            $rev2                  = new Revise_ReviseStruct();
            $rev2->err_quality     = Constants_Revise::NONE;
            $rev2->err_style       = Constants_Revise::NONE;
            $rev2->err_terminology = Constants_Revise::NONE;
            $rev2->err_typing      = Constants_Revise::NONE;
            $rev2->err_translation = Constants_Revise::NONE;
        }

        $rev1 = $this->convertToBinaryValues( $rev1 );
        $rev2 = $this->convertToBinaryValues( $rev2 );

        $diff = array();
        foreach ( $rev2 as $key => $val ) {
            $diff[$key] = $val - $rev1[$key];
        }

        return $diff;
    }

    private function convertToBinaryValues( Revise_ReviseStruct $obj ) {
        $result = array();
        foreach ( $obj as $key => $value ) {
            if ( strpos( $key, "err_" ) > -1 ) {
                //convert the string value into a numeric one (mapped).
                //if the value is not zero, force it to 1.
                $result[ $key ] = (Constants_Revise::$const2clientValues[ $value ] == 0) ? 0 : 1;
            }
        }

        return $result;
    }


}