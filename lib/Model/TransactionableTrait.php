<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/04/2019
 * Time: 10:50
 */

/**
 * Trait TransactionableTrait
 *
 * This trait is to be used whenever the class expects to use a database transaction.
 * The class may not be aware of the fact that a transaction is already started, so
 * it should not call begin transaction if a transaction is already open and should
 * not call commit transaction if a transaction was not started by itself. Assuming
 * the containing code will take care for committing the larger transaction.
 *
 */
trait TransactionableTrait {

    private $__transactionStarted = false ;

    protected function openTransaction() {
        if ( ! Database::obtain()->getConnection()->inTransaction() ) {
            Database::obtain()->begin();
            $this->__transactionStarted = true ;
        }
    }

    protected function commitTransaction() {
        if ( $this->__transactionStarted ) {
            Database::obtain()->commit() ;
            $this->__transactionStarted = false;
        }
    }

    /**
     * TODO: not sure this is actually sane
     */
    protected function rollbackTransaction() {
        if ( $this->__transactionStarted ) {
            Database::obtain()->rollback();
            $this->__transactionStarted = false;
        }
    }

}