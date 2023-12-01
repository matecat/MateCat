<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;

class ProjectStatsController extends KleinController
{
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function index()
    {
        // filtro per:
        // - invervallo di date
        // - per singolo utente uid
        // - per singolo utente email

        // projects created: 434343
        // from_api: 434343
        // percent_from_api: 44 (1-100)

        $a = 333;
        $a = 333;
        $a = 333;
        $a = 333;
        $a = 333;

        $this->response->json([
            'projects_created' => 423432432,
            'from_api' => 434343,
            'percent_from_api' => 44,
        ]);
        $this->response->code(200);
    }
}

