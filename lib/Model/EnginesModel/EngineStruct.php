<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 14.54
 */
class EnginesModel_EngineStruct
        extends DataAccess_AbstractDaoObjectStruct
        implements DataAccess_IDaoStruct, ArrayAccess {

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;


    /**
     * @var string A string from the ones in Constants_EngineType
     * @see Constants_EngineType
     */
    public $type;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $base_url;

    /**
     * @var string
     */
    public $translate_relative_url;

    /**
     * @var string
     */
    public $contribute_relative_url;

    /**
     * @var string
     */
    public $update_relative_url;

    /**
     * @var string
     */
    public $delete_relative_url;

    /**
     * @var array
     */
    public $others;

    /**
     * @var string
     */
    public $class_load;


    /**
     * @var array
     */
    public $extra_parameters;

    /**
     * @var int
     */
    public $google_api_compliant_version;

    /**
     * @var int
     */
    public $penalty;

    /**
     * @var int 0 or 1
     */
    public $active;

    /**
     * @var int
     */
    public $uid;

    /**
     * An empty struct
     * @return EnginesModel_EngineStruct
     */
    public static function getStruct() {
        return new EnginesModel_EngineStruct();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     *                      An offset to check for.
     *                      </p>
     *
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists( $offset ) {
        return property_exists( $this, $offset );
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     *                      The offset to retrieve.
     *                      </p>
     *
     * @return mixed Can return all value types.
     */
    public function offsetGet( $offset ) {
        return $this->$offset;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     *                      The offset to assign the value to.
     *                      </p>
     * @param mixed $value  <p>
     *                      The value to set.
     *                      </p>
     *
     * @return void
     */
    public function offsetSet( $offset, $value ) {
        $this->$offset = $value;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     *                      The offset to unset.
     *                      </p>
     *
     * @return void
     */
    public function offsetUnset( $offset ) {
        $this->$offset = null;
    }

    /**
     * Cast an Engine to String. Useful for engine comparison inside a list ( catController )
     */
    public function __toString(){
        return $this->id . $this->name . $this->description;
    }

    /**
     * If, for some reasons, extra_parameters
     * if NOT an array but a JSON
     * this function normalize it
     *
     * @return array|mixed
     */
    public function getExtraParamsAsArray()
    {
        if(is_array($this->extra_parameters)){
            return $this->extra_parameters;
        }

        if(empty($this->extra_parameters) or $this->extra_parameters === null){
            return [];
        }

        return json_decode($this->extra_parameters, true);
    }

}