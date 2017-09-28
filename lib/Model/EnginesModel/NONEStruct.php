<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/08/17
 * Time: 15.02
 *
 */

class EnginesModel_NONEStruct extends EnginesModel_EngineStruct {

    public $id                           = 0;
    public $name                         = 'NONE';
    public $type                         = 'NONE';
    public $description                  = 'No MT';
    public $base_url                     = "";
    public $translate_relative_url       = "";
    public $contribute_relative_url      = "";
    public $update_relative_url          = "";
    public $delete_relative_url          = "";
    public $others                       = [];
    public $extra_parameters             = [];
    public $class_load                   = 'NONE';
    public $google_api_compliant_version = null;
    public $penalty                      = 100;
    public $active                       = 0;
    public $uid                          = null;

}