<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use PayableRates\CustomPayableRateDao;
use PayableRates\CustomPayableRateStruct;
use Validator\Errors\JSONValidatorError;

class DeepLController extends KleinController
{
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function glossaries()
    {

    }
}