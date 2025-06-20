<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/04/17
 * Time: 18.50
 *
 */

namespace Translators;


class TranslatorProfilesStruct extends \DataAccess\AbstractDaoSilentStruct implements \DataAccess\IDaoStruct {

    public $id;
    public $uid_translator;
    public $is_revision;
    public $translated_words;
    public $revised_words;
    public $source;
    public $target;

}