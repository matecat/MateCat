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

        $this->diffRevisions($oldRevision, $newRevision);

    }

    private function diffRevisions( Revise_ReviseStruct $rev1, Revise_ReviseStruct $rev2 ) {
        if ( empty( $rev1 ) ) {
            $rev1                  = new Revise_ReviseStruct();
            $rev1->err_language    = Constants_Revise::NONE;
            $rev1->err_style       = Constants_Revise::NONE;
            $rev1->err_terminology = Constants_Revise::NONE;
            $rev1->err_typing      = Constants_Revise::NONE;
            $rev1->err_translation = Constants_Revise::NONE;
        }

        if ( empty( $rev2 ) ) {
            $rev2                  = new Revise_ReviseStruct();
            $rev2->err_language    = Constants_Revise::NONE;
            $rev2->err_style       = Constants_Revise::NONE;
            $rev2->err_terminology = Constants_Revise::NONE;
            $rev2->err_typing      = Constants_Revise::NONE;
            $rev2->err_translation = Constants_Revise::NONE;
        }

        $rev1 = $this->convertToCorrespondingValues( $rev1 );
        $rev2 = $this->convertToCorrespondingValues( $rev2 );

        foreach ( $rev2 as $key => $val ) {

            $real_error_key = str_replace( 'err_', '', $key );

            /**
             * Conditions: IMPORTANT
             *
             * K2 == K1
             * K2 > K1 AND K1  = 0
             * K2 > K1 AND K1 != 0
             * K2 < K1 AND K2  = 0
             * K2 < K1 AND K2 != 0
             *
             */
            if( $rev2[ $key ] == $rev1[ $key ] ) {

                    //key are unchanged, the differential is null // NOP
                    $this->{ $real_error_key . "_min" } = 0;
                    $this->{ $real_error_key . "_maj" } = 0;

            }
            elseif( $rev2[ $key ] > $rev1[ $key ] && $rev1[ $key ] == 0 ) {

                // new key is set and the old one was none
                // what is the new value?? Check
                switch( $rev2[ $key ] ) {
                    case Constants_Revise::CLIENT_VALUE_MAJOR:
                        $this->{ $real_error_key . "_min" } = 0;
                        $this->{ $real_error_key . "_maj" } = 1;
                        break;
                    case Constants_Revise::CLIENT_VALUE_MINOR:
                        $this->{ $real_error_key . "_min" } = 1;
                        $this->{ $real_error_key . "_maj" } = 0;
                        break;
                }

            }
            elseif( $rev2[ $key ] > $rev1[ $key ] && $rev1[ $key ] != 0 ){

                // new key is MAJOR and the old key was MINOR
                $this->{ $real_error_key . "_min" } = -1;
                $this->{ $real_error_key . "_maj" } = 1;

            }
            elseif( $rev2[ $key ] < $rev1[ $key ] && $rev2[ $key ] == 0  ){

                // new key is LESSER THAN the old one and it is NONE ( so the old one was MINOR or MAJOR )
                // what is the OLD value?? Check
                switch( $rev1[ $key ] ) {
                    case Constants_Revise::CLIENT_VALUE_MAJOR:
                        $this->{ $real_error_key . "_min" } = 0;
                        $this->{ $real_error_key . "_maj" } = -1;
                        break;
                    case Constants_Revise::CLIENT_VALUE_MINOR:
                        $this->{ $real_error_key . "_min" } = -1;
                        $this->{ $real_error_key . "_maj" } = 0;
                        break;
                }

            }
            elseif( $rev2[ $key ] < $rev1[ $key ] && $rev2[ $key ] != 0 ){

                //new key is LESSER than the previous and it is NOT NONE ( so it is MINOR )
                $this->{ $real_error_key . "_min" } = 1;
                $this->{ $real_error_key . "_maj" } = -1;

            }
            else {
                //????? this is impossible
                //NOP
                $this->{ $real_error_key . "_min" } = 0;
                $this->{ $real_error_key . "_maj" } = 0;
            }

        }

    }

    private function convertToCorrespondingValues( Revise_ReviseStruct $obj ) {
        $result = array();
        foreach ( $obj as $key => $value ) {
            if ( strpos( $key, "err_" ) > -1 ) {
                //convert the string value into a numeric one (mapped).
                //if the value is not zero, get it.
                $result[ $key ] = Constants_Revise::$const2clientValues[ $value ];
            }
        }

        return $result;
    }


}