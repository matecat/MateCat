<?php


namespace Translations ;

class WarningStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id_job ;
    public $id_segment ;
    public $scope ;
    public $severity ;
    public $data ;

}