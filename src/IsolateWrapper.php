<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

final class IsolateWrapper implements EntityManager
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getLocked(string $entityClassName, $entityId): ?object
    {
        return $this->entityManager->getLocked($entityClassName, $entityId);
    }

    public function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    public function save(object $entity): void
    {
        $this->entityManager->save($entity);
    }
}