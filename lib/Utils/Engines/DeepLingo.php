<?php

/**
 * Created by PhpStorm.
 * @property string client_secret
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/03/15
 * Time: 12.10
 * 
 */

class Engines_DeepLingo extends Engines_Moses implements Engines_EngineInterface {

    protected $_config = array(
            'segment'     => null,
            'translation' => null,
            'source'      => null,
            'target'      => null,
            'id_user'     => null,
    );

}