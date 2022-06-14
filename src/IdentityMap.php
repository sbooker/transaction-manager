<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

final class IdentityMap implements TransactionHandler
{
    private array $map = [];
    private array $entityIdentifiers = [];
    private TransactionHandler $transactionHandler;

    public function __construct(TransactionHandler $transactionHandler)
    {
        $this->transactionHandler = $transactionHandler;
    }

    public function begin(): void
    {
        $this->transactionHandler->begin();
    }

    public function persist(object $entity): void
    {
        $this->transactionHandler->persist($entity);
    }

    public function detach(object $entity): void
    {
        $oid = spl_object_hash($entity);

        if (isset($this->entityIdentifiers[$oid])) {
            if (isset($this->map[get_class($entity)][$this->entityIdentifiers[$oid]])) {
                unset($this->map[get_class($entity)][$this->entityIdentifiers[$oid]]);
            }
            unset($this->entityIdentifiers[$oid]);
        }

        $this->transactionHandler->detach($entity);
    }

    public function commit(array $entities): void
    {
        $this->transactionHandler->commit($entities);
    }

    public function rollback(): void
    {
        $this->transactionHandler->rollback();
    }

    public function clear(): void
    {
        $this->map = [];
        $this->transactionHandler->clear();
    }

    public function getLocked(string $entityClassName, $entityId): ?object
    {
        $id = $this->stringifyEntityId($entityId);

        if (isset($this->map[$entityClassName][$id])) {
            return $this->map[$entityClassName][$id];
        }

        $entity = $this->transactionHandler->getLocked($entityClassName, $entityId);
        if (null === $entity) {
            return null;
        }

        $this->map[$entityClassName][$id] = $entity;
        $this->entityIdentifiers[spl_object_hash($entity)] = $id;

        return $this->map[$entityClassName][$id];
    }

    /**
     * @param mixed $entityId
     * @return string
     */
    private function stringifyEntityId($entityId): string
    {
        switch (true) {
            case is_scalar($entityId):
            case is_object($entityId):
                return (string)$entityId;
            case is_array($entityId):
                return implode('_', array_map(fn($part): string => $this->stringifyEntityId($part), $entityId));
            default:
                throw new \RuntimeException("Invalid identifier type ");
        }
    }
}