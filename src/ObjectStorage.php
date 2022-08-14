<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

/**
 * @internal
 */
final class ObjectStorage
{
    /** @var array<int, object>  */
    private array $store = [];

    public function add(object $object): void
    {
        $this->store[spl_object_hash($object)] = $object;
    }

    public function clear(): void
    {
        $this->store = [];
    }

    public function remove(object $object): void
    {
        unset($this->store[spl_object_hash($object)]);
    }

    public function getAll(): array
    {
        return $this->store;
    }
}