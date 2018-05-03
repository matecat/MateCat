<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/11/2017
 * Time: 11:32
 */

namespace Translations;


use DataAccess_AbstractDao;

class TranslationVersionDataDao extends DataAccess_AbstractDao {
    const TABLE                     = 'translation_version_data' ;
    protected static $primary_keys  = [ 'id' ];





}