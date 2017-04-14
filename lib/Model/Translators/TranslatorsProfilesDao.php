<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 15.50
 *
 */

namespace Translators;


class TranslatorsProfilesDao extends \DataAccess_AbstractDao {

    const TABLE       = "translator_profiles";
    const STRUCT_TYPE = "TranslatorProfilesStruct";

    protected static $auto_increment_fields = array( 'id' );
    protected static $primary_keys          = array( 'id' );

}