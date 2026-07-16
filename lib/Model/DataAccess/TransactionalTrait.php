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

    protected static bool $__transactionStarted = false;

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
            static::$__transactionStarted = true;
        }
    }

    /**
     * @throws \PDOException
     */
    protected function commitTransaction(): void
    {
        if (static::$__transactionStarted) {
            $this->getTransactionalDatabase()->commit();
            static::$__transactionStarted = false;
        }
    }

    /**
     * @throws \PDOException
     */
    protected function rollbackTransaction(): void
    {
        if (static::$__transactionStarted) {
            $this->getTransactionalDatabase()->rollback();
            static::$__transactionStarted = false;
        }
    }

}