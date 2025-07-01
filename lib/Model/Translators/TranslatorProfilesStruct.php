<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/04/17
 * Time: 18.50
 *
 */

namespace Model\Translators;


use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class TranslatorProfilesStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int   $id               = null;
    public int    $uid_translator;
    public int    $is_revision;
    public int    $translated_words = 0;
    public int    $revised_words    = 0;
    public string $source;
    public string $target;

}