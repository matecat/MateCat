<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 19/01/15
 * Time: 18.14
 */

class Revise_ReviseStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct{

    /**
     * @var int
     */
    public $id_job;

    /**
     * @var int
     */
    public $id_segment;

    /**
     * @var string A string from the ones in Constants_Revise
     * @see Constants_Revise
     */
    public $err_typing;

    /**
     * @var string A string from the ones in Constants_Revise
     * @see Constants_Revise
     */
    public $err_translation;

    /**
     * @var string A string from the ones in Constants_Revise
     * @see Constants_Revise
     */
    public $err_terminology;

    /**
     * @var string A string from the ones in Constants_Revise
     * @see Constants_Revise
     */
    public $err_language;

    /**
     * @var string A string from the ones in Constants_Revise
     * @see Constants_Revise
     */
    public $err_style;

    /**
     * @var string The original translation
     */
    public $original_translation;

    /**
     * An empty struct
     * @return Revise_ReviseStruct
     */
    public static function getStruct(){
        return new Revise_ReviseStruct();
    }

    /**
     * Set default values for the current struct only if values are missing
     * @param $struct Revise_ReviseStruct
     *
     * @return Revise_ReviseStruct
     */
    public static function setDefaultValues(Revise_ReviseStruct $struct){
        //TODO: improve this method. What if this structure contains non-standard fields?

        $allowed_values = array(
                Constants_Revise::NONE,
                Constants_Revise::MINOR,
                Constants_Revise::MAJOR
        );

        foreach ($struct as $key => $val) {
            if(strpos($key, "err_") > -1){
                //if $fieldVal is not one of the accepted values, force it to "none"
                if ( empty($val) || !in_array( $val, $allowed_values ) ) {
                    $val = Constants_Revise::NONE;
                }
                $struct->{$key} = $val;
            }
            else if( ($key == "id_job" || $key == "id_segment") && empty($val) ){
                $struct->{$key} = -1;
            }
        }

        return $struct;
    }
}