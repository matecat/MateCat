<?php


namespace API\V2 ;

use \Log ;
use \Database ;
use \Translations_SegmentTranslationDao as Dao ;
use \Translations_SegmentTranslationStruct as Struct ;

class TranslationController extends ProtectedKleinController {
    private $validator ;

    public function show() {

    }

    public function update() {
        $params = $this->request->param('translation');
        $attrs = $this->validator->translation->attributes() ;

        $new_struct = new Struct( array_merge( $attrs, $params ) );
        Dao::updateStruct( $new_struct, array('fields' => array_keys($params) ) );

        $this->response->code(200);
    }

    public function afterConstruct() {
        $this->validator = new Validators\SegmentTranslation( $this->request );
    }

    public function validateRequest() {
        $this->validator->validate();
    }

}
