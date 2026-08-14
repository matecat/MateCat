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
trait TransactionalTrait
{

    /**
     * Whether this object opened the transaction it is inside of.
     *
     * Per object, not per class. It used to be static, which made every instance of the using
     * class share one flag: two of them nested, and the inner commit ended the outer one's
     * transaction halfway through its work. A flag left set by a failure also outlived the
     * object, so in a daemon the next commit on that class committed whatever transaction
     * happened to be open. Neither can happen to a property that dies with its object.
     */
    protected bool $__transactionStarted = false;

    /**
     * The database the transaction runs on. Each host returns its own injected
     * handle so the transaction and the host's queries share one connection.
     */
    abstract protected function getTransactionalDatabase(): IDatabase;

    /**
     * @throws \PDOException
     */
    protected function openTransaction(): void
    {
        $database = $this->getTransactionalDatabase();
        if (!$database->getConnection()->inTransaction()) {
            $database->begin();
            $this->__transactionStarted = true;
        }
    }

    /**
     * @throws \PDOException
     */
    protected function commitTransaction(): void
    {
        if (!$this->__transactionStarted) {
            return;
        }

        // Cleared before the command is issued, not after: a commit that throws would otherwise
        // leave the object believing it still owns a transaction that is no longer there.
        $this->__transactionStarted = false;

        $database = $this->getTransactionalDatabase();
        if ($database->getConnection()->inTransaction()) {
            $database->commit();
        }
    }

    /**
     * @throws \PDOException
     */
    protected function rollbackTransaction(): void
    {
        if (!$this->__transactionStarted) {
            return;
        }

        $this->__transactionStarted = false;

        $database = $this->getTransactionalDatabase();
        if ($database->getConnection()->inTransaction()) {
            $database->rollback();
        }
    }

}