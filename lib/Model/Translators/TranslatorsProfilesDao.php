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

    protected static $auto_increment_field = array( 'id' );
    protected static $primary_keys         = array( 'id' );

    protected static $_query_by_uid_src_trg_rev = "
        SELECT * FROM translator_profiles 
        WHERE uid_translator = :uid_translator 
        AND source = :source 
        AND target = :target 
        AND is_revision = :is_revision ";

    /**
     * Get the value by UNIQUE Key
     *
     * @param TranslatorProfilesStruct $profile
     *
     * @return TranslatorProfilesStruct
     * @internal param $id
     *
     */
    public function getByProfile( TranslatorProfilesStruct $profile ) {

        $stmt = $this->_getStatementForCache( self::$_query_by_uid_src_trg_rev );

        return @$this->_fetchObject( $stmt,
                $profile,
                [
                        'uid_translator' => $profile->uid_translator,
                        'source'         => $profile->source,
                        'target'         => $profile->target,
                        'is_revision'    => $profile->is_revision,

                ]
        )[ 0 ];
    }

}