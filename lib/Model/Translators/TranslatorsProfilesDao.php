<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 15.50
 *
 */

namespace Model\Translators;


use Model\DataAccess\AbstractDao;
use ReflectionException;

class TranslatorsProfilesDao extends AbstractDao {

    const TABLE       = "translator_profiles";
    const STRUCT_TYPE = TranslatorProfilesStruct::class;

    protected static array $auto_increment_field = [ 'id' ];
    protected static array $primary_keys         = [ 'id' ];

    protected static string $_query_by_uid_src_trg_rev = "
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
     * @throws ReflectionException
     * @internal param $id
     *
     */
    public function getByProfile( TranslatorProfilesStruct $profile ): ?TranslatorProfilesStruct {

        $stmt = $this->_getStatementForQuery( self::$_query_by_uid_src_trg_rev );

        return $this->_fetchObjectMap( $stmt,
                TranslatorProfilesStruct::class,
                [
                        'uid_translator' => $profile->uid_translator,
                        'source'         => $profile->source,
                        'target'         => $profile->target,
                        'is_revision'    => $profile->is_revision,

                ]
        )[ 0 ] ?? null;
    }

}