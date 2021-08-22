<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

final class TransactionManager
{
    private TransactionHandler $transactionHandler;
    private int $nestingLevel = 0;


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
        $this->increaseNestingLevel();
        if ($this->isTopNestingLevel()) {
            $this->transactionHandler->begin();
        }
    }

    private function commit(): void
    {
        if ($this->isTopNestingLevel()) {
            $this->transactionHandler->commit();
        }
        $this->decreaseNestingLevel();
    }

    private function rollback(): void
    {
        if ($this->isTopNestingLevel()) {
            $this->transactionHandler->rollBack();
        }
        $this->decreaseNestingLevel();
    }

    private function increaseNestingLevel(): void
    {
        $this->nestingLevel+= 1;
    }

    private function decreaseNestingLevel(): void
    {
        $this->nestingLevel-= 1;
    }

    private function isTopNestingLevel(): bool
    {
        return $this->nestingLevel <= 1;
    }
}