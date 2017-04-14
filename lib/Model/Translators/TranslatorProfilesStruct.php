<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/04/17
 * Time: 18.50
 *
 */

namespace Translators;


use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class TranslatorProfilesStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id;
    public $uid_translator;
    public $is_revision;
    public $translated_words;
    public $revised_words;
    public $source;
    public $target;

}