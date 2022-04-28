<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

/**
 * @internal
 */
final class ObjectTransactionHandler
{
    private TransactionHandler $transactionHandler;
    private ?PreCommitEntityProcessor $preCommitEntityProcessor;
    private int $nestingLevel = 0;
    private ObjectStorage $objectsToSave;
    private ObjectStorage $objectsToPersist;

    public function __construct(TransactionHandler $transactionHandler, ?PreCommitEntityProcessor $preCommitEntityProcessor = null)
    {
        $this->transactionHandler = new IdentityMap($transactionHandler);
        $this->preCommitEntityProcessor = $preCommitEntityProcessor;
        $this->objectsToSave = new ObjectStorage();
        $this->objectsToPersist = new ObjectStorage();
    }

    public function begin(): void
    {
        $this->increaseNestingLevel();
        if ($this->isTopNestingLevel()) {
            $this->transactionHandler->begin();
        }
    }

    public function persist(object $entity): void
    {
        if (!$this->isInTransaction()) {
            throw new \RuntimeException('Persisting entity available only in transaction');
        }

        $this->objectsToPersist->addAtLevel($this->nestingLevel, $entity);
    }

    public function save(object $entity): void
    {
        if (!$this->isInTransaction()) {
            throw new \RuntimeException('Saving entity available only in transaction');
        }

        $this->objectsToSave->addAtLevel($this->nestingLevel, $entity);
    }

    /**
     * @param string $entityClassName
     * @param mixed $entityId
     */
    public function getLocked(string $entityClassName, $entityId): ?object
    {
        if (!$this->isInTransaction()) {
            throw new \RuntimeException('Locked entity available only in transaction');
        }

        $entity = $this->transactionHandler->getLocked($entityClassName, $entityId);
        if (null !== $entity) {
            $this->objectsToSave->addAtLevel($this->nestingLevel, $entity);
        }

        return $entity;
    }

    public function commit(): void
    {
        if ($this->isTopNestingLevel()) {
            $this->doCommit();
        }

        $this->decreaseNestingLevel();
    }

    private function doCommit(): void
    {
        $this->processEntities();
        $this->persistAll();
        $this->saveAll();
        $this->clear();
    }

    private function processEntities(): void
    {
        if (null === $this->preCommitEntityProcessor) {
            return;
        }

        foreach ($this->getAllStoredEntities() as $entity) {
            $this->preCommitEntityProcessor->process($entity);
        }
    }

    private function persistAll(): void
    {
        foreach ($this->objectsToPersist->getFromAllLevels() as $entity) {
            $this->transactionHandler->persist($entity);
        }
    }

    private function saveAll(): void
    {
        $this->transactionHandler->commit($this->getAllStoredEntities());
    }

    /**
     * @return array<object>
     */
    private function getAllStoredEntities(): array
    {
        return array_merge(
            $this->objectsToPersist->getFromAllLevels(),
            $this->objectsToSave->getFromAllLevels()
        );
    }

    public function rollback(): void
    {
        if ($this->isTopNestingLevel()) {
            $this->transactionHandler->rollback();
        }

        $this->objectsToPersist->clearLevelsLowestAndEqualsThan($this->nestingLevel);
        $this->objectsToSave->clearLevelsLowestAndEqualsThan($this->nestingLevel);

        $this->decreaseNestingLevel();
    }

    public function clear(): void
    {
        $this->clearStorages();
        $this->transactionHandler->clear();
    }

    private function clearStorages(): void
    {
        $this->objectsToPersist->clear();
        $this->objectsToSave->clear();
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

    private function isInTransaction(): bool
    {
        return $this->nestingLevel >= 1;
    }
}