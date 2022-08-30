<?php

namespace API\App;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;

class GlossaryController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

// REQUEST DALLA UI
//{
//"id_segment": 12345,
//"id_client": "XXXXXX"
//"id_job": 123456,
//"password": "dndndndnd" <-- the current Phase password,
//"source_language": "en-US",
//"target_language": "it-IT"
//}

    public function create()
    {}

    public function delete()
    {}

    public function edit()
    {}

    public function show()
    {}

// REQUEST DALLA UI
//{
//"sentence": "Search this",
//"id_client": "XXXXXX"
//"id_job": 123456,
//"password": "dndndndnd" <-- the current Phase password,
//"source_language": "en-US", <<- when searching from target invert languages
//"target_language": "it-IT"
//}

    public function search()
    {}
}

// IN TUTTI I CASI A MM VA INVIATO QUESTO
//{
//    "source": "xxxxxx",
//    "source_language": "en-US",
//    "target_language": "it-IT",
//    "keys": [ "xxx", "yyy" ]
//}