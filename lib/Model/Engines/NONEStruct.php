<?php

namespace Model\Engines;

use Utils\Engines\NONE;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/08/17
 * Time: 15.02
 *
 */
class NONEStruct extends EngineStruct {

    public ?int    $id                           = 0;
    public ?string $name                         = 'NONE';
    public ?string $type                         = 'NONE';
    public ?string $description                  = 'No MT';
    public ?string $base_url                     = "";
    public ?string $translate_relative_url       = "";
    public ?string $contribute_relative_url      = "";
    public ?string $update_relative_url          = "";
    public ?string $delete_relative_url          = "";
    public         $others                       = [];
    public         $extra_parameters             = [];
    public ?string $class_load                   = None::class;
    public ?int    $google_api_compliant_version = null;
    public ?int    $penalty                      = 100;
    public ?bool   $active                       = false;
    public ?int    $uid                          = null;

}