<?php

namespace Model\OwnerFeatures;
use BasicFeatureStruct;

class OwnerFeatureStruct extends BasicFeatureStruct {

    public $id;
    public $uid;
    public $id_team;
    public $options;
    public $last_update;
    public $create_date;
    public $enabled;

    public function getOptions() {
        return json_decode( $this->options, true );
    }

}
