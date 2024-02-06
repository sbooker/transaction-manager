<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

interface TransactionHandler
{
    public function begin(): void;

    public function persist(object $entity): void;

    public function detach(object $entity): void;

    /**
     * @throws UniqueConstraintViolation
     */
    public function commit(array $entities): void;

    public function rollback(): void;

    public function clear(): void;

    /**
     * @param string $entityClassName
     * @param mixed $entityId
     */
    public function getLocked(string $entityClassName, $entityId): ?object;
}