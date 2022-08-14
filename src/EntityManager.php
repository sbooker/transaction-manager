<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

interface EntityManager
{
    /**
     * @template T
     * @psalm-param class-string<T> $entityClassName
     * @psalm-return T|null
     *
     * @param mixed $entityId
     */
    public function getLocked(string $entityClassName, $entityId): ?object;

    public function persist(object $entity): void;

    public function save(object $entity): void;
}