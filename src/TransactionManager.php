<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

final class TransactionManager
{
    private ObjectTransactionHandler $transactionHandler;

    public function __construct(TransactionHandler $transactionHandler, ?PreCommitEntityProcessor $preCommitEntityProcessor = null)
    {
        $this->transactionHandler = new ObjectTransactionHandler($transactionHandler, $preCommitEntityProcessor);
        if ($preCommitEntityProcessor instanceof TransactionManagerAware) {
            $preCommitEntityProcessor->setTransactionManager($this);
        }
    }

    /**
     * @return mixed
     *
     * @throws UniqueConstraintViolation
     * @throws \Throwable
     */
    public function transactional(callable $func)
    {
        $this->begin();

        try {
            $result = call_user_func($func);
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
        // Make commit outside try-catch block to avoid rollback on exception thrown by commit().
        try {
            $this->commit();
        } catch (UniqueConstraintViolation $e) {
            $this->rollback();
            throw $e;
        }

        return $result;
    }

    public function persist(object $entity): void
    {
        $this->transactionHandler->persist($entity);
    }

    public function save(object $entity): void
    {
        $this->transactionHandler->save($entity);
    }

    /**
     * @template T
     * @psalm-param class-string<T> $entityClassName
     * @psalm-return T|null
     *
     * @param mixed $entityId
     */
    public function getLocked(string $entityClassName, $entityId): ?object
    {
        return $this->transactionHandler->getLocked($entityClassName, $entityId);
    }

    public function clear(): void
    {
        $this->transactionHandler->clear();
    }

    private function begin(): void
    {
        $this->transactionHandler->begin();
    }

    /**
     * @throws UniqueConstraintViolation
     */
    private function commit(): void
    {
        $this->transactionHandler->commit();
    }

    private function rollback(): void
    {
        $this->transactionHandler->rollback();
    }
}