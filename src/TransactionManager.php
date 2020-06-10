<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

final class TransactionManager
{
    /** @var TransactionHandler  */
    private $transactionHandler;


    public function __construct(TransactionHandler $transactionHandler)
    {
        $this->transactionHandler = $transactionHandler;
    }

    /**
     * @return mixed
     *
     * @throws \Throwable
     */
    public function transactional(callable $func)
    {
        $this->begin();

        $result = null;
        try {
            $result = call_user_func($func);
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
        // Make commit outside of try-catch block to avoid rollback on exception thrown by commit().
        $this->commit();

        return $result;
    }

    public function clear(): void
    {
        $this->transactionHandler->clear();
    }

    private function begin(): void
    {
        $this->transactionHandler->begin();
    }

    private function commit(): void
    {
        $this->transactionHandler->commit();
    }

    private function rollback(): void
    {
        $this->transactionHandler->rollBack();
    }
}