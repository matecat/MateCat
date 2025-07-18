<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/04/2019
 * Time: 10:50
 */

namespace Model\DataAccess;

/**
 * Trait TransactionalTrait
 *
 * This trait is intended for use in classes that interact with database transactions.
 *
 * The class using this trait may not be aware of an ongoing transaction, so
 * it should not call `beginTransaction` if a transaction is already open.
 *
 * Additionally, it should not call `commitTransaction` if it did not initiate the transaction itself.
 *
 * It is assumed that the surrounding code will handle committing the larger transaction.
 *
 */
trait TransactionalTrait {

    private static bool $__transactionStarted = false;

    protected function openTransaction() {
        if ( !Database::obtain()->getConnection()->inTransaction() ) {
            Database::obtain()->begin();
            static::$__transactionStarted = true;
        }
    }

    protected function commitTransaction() {
        if ( static::$__transactionStarted ) {
            Database::obtain()->commit();
            static::$__transactionStarted = false;
        }
    }

    /**
     * TODO: not sure this is actually sane
     */
    protected function rollbackTransaction() {
        if ( static::$__transactionStarted ) {
            Database::obtain()->rollback();
            static::$__transactionStarted = false;
        }
    }

}