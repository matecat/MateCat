<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 29/09/14
 * Time: 17.55
 */

/**
 * Interface IDaoStruct A generic interface that will be used by any DataAccess_AbstractDao extended object
 * @see AbstractDao
 */

interface DataAccess_IDaoStruct {

    public function __construct(Array $array_params = array());

} 