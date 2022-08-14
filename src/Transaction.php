<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

/**
 * @internal
 */
final class Transaction implements EntityManager
{
    private TransactionHandler $transactionHandler;
    private ?PreCommitEntityProcessor $preCommitEntityProcessor;
    private ?self $parent;
    /** @var array<self> */
    private array $nested = [];
    private ObjectStorage $entitiesToUpdate;
    private ObjectStorage $entitiesToInsert;
    private bool $closed = false;
    private IsolateWrapper $wrapper;

    private function __construct(TransactionHandler $transactionHandler, ?PreCommitEntityProcessor $preCommitEntityProcessor = null, ?self $parent = null)
    {
        $this->transactionHandler = $transactionHandler;
        $this->preCommitEntityProcessor = $preCommitEntityProcessor;
        $this->parent = $parent;
        $this->entitiesToInsert = new ObjectStorage();
        $this->entitiesToUpdate = new ObjectStorage();
        $this->wrapper = new IsolateWrapper($this);
    }

    public static function begin(TransactionHandler $transactionHandler, ?PreCommitEntityProcessor $preCommitEntityProcessor = null): self
    {
        $transaction = new self($transactionHandler, $preCommitEntityProcessor);
        $transaction->transactionHandler->begin();

        return $transaction;
    }

    public function beginNested(): Transaction
    {
        $nested = $this->getOpenNested();

        if (null !== $nested) {
            return $nested->beginNested();
        }

        $transaction = new self($this->transactionHandler, $this->preCommitEntityProcessor, $this);
        $this->nested[] = $transaction;


        return $transaction;
    }

    private function getOpenNested(): ?self
    {
        foreach ($this->nested as $transaction) {
            if (!$transaction->closed) {
                return $transaction;
            }
        }

        return null;
    }

    public function persist(object $entity): void
    {
        if ($this->closed) {
            throw new \RuntimeException('Can not persist in closed transaction');
        }
        $this->entitiesToInsert->add($entity);
    }

    public function commit(): void
    {
        if ($this->isTopNestingLevel()) {
            $this->preprocess();
            $this->doPersist();
            $this->saveAll();
            $this->transactionHandler->commit();
            $this->clear();
        }

        $this->closed = true;
    }

    private function preprocess(): void
    {
        if (null === $this->preCommitEntityProcessor) {
            return;
        }

        $this->forNested(fn(self $transaction) => $transaction->preprocess());

        foreach ($this->getAllStoredEntities() as $entity) {
            $this->preCommitEntityProcessor->process($this->isolated(),  $entity);
        }
    }

    private function doPersist(): void
    {
        $this->forNested(fn(self $transaction) => $transaction->doPersist());

        foreach ($this->entitiesToInsert->getAll() as $toInsert) {
            $this->transactionHandler->persist($toInsert);
        }
    }

    private function saveAll(): void
    {
        $this->forNested(fn(self $transaction) => $transaction->saveAll());

        foreach ($this->entitiesToUpdate->getAll() as $toUpdate) {
            $this->transactionHandler->save($toUpdate);
        }
    }

    private function getAllStoredEntities(): array
    {
        return array_merge($this->entitiesToInsert->getAll(), $this->entitiesToUpdate->getAll());
    }

    private function clear(): void
    {
        $this->forNested(fn(self $transaction) => $transaction->clear());

        $this->entitiesToInsert->clear();
        $this->entitiesToUpdate->clear();
        $this->nested = [];

        if (null === $this->parent) {
            $this->transactionHandler->clear();
        }
    }

    public function rollback(): void
    {
        $this->forNested(fn(self $transaction) => $transaction->rollback());

        foreach ($this->getAllStoredEntities() as $entity) {
            $this->transactionHandler->detach($entity);
        }

        $this->clear();

        if ($this->isTopNestingLevel()) {
            $this->transactionHandler->rollback();
        }
    }

    private function forNested(callable $fn): void
    {
        foreach ($this->nested as $transaction) {
            $fn($transaction);
        }
    }

    public function save(object $entity): void
    {
        $this->entitiesToUpdate->add($entity);
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
        $entity = $this->transactionHandler->getLocked($entityClassName, $entityId);
        if (null !== $entity) {
            $this->entitiesToUpdate->add($entity);
        }

        return $entity;
    }

    public function isolated(): EntityManager
    {
        return $this->wrapper;
    }

    private function isTopNestingLevel(): bool
    {
        return null === $this->parent;
    }
}