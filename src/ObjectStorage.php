<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

/**
 * @internal
 */
final class ObjectStorage
{
    /** @var array<int, <array<object>>  */
    private array $store = [];

    public function addAtLevel(int $level, object $object): void
    {
        $this->store[$level][spl_object_hash($object)] = $object;
    }

    public function clear(): void
    {
        $this->store = [];
    }

    public function getAndClearLevelsLowestAndEqualsThan(int $level): array
    {
        $result = [];
        
        for($i = $level; $i <= $this->getMaxLevel(); $i++) {
            if (isset($this->store[$i])) {
                $result = array_merge($result, $this->store[$i]);
                unset($this->store[$i]);
            }
        }
        
        return array_values($result);
    }

    private function getMaxLevel(): int
    {
        $levels = array_keys($this->store);
        if (count($levels) > 0) {
            return max($levels);
        }

        return 0;
    }

    public function getFromAllLevels(): array
    {
        return
            array_values(
                array_reduce(
                    $this->store,
                    fn(array $carry, array $objectsAtLevel): array => array_merge($carry, $objectsAtLevel),
                    []
                )
            );
    }
}