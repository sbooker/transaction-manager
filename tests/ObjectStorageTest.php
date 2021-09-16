<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager\Tests;

use PHPUnit\Framework\TestCase;
use Sbooker\TransactionManager\ObjectStorage;

/**
 * @covers \Sbooker\TransactionManager\ObjectStorage
 */
final class ObjectStorageTest extends TestCase
{
    public function testClearingAtLevel(): void
    {
        $storage = new ObjectStorage();
        $firstObject = (object)['p' => 'first'];
        $secondObject = (object)['p' => 'second'];
        $storage->addAtLevel(1, $firstObject);
        $storage->addAtLevel(2, $secondObject);

        $storage->clearLevelsLowestAndEqualsThan(2);

        $this->assertEquals([$firstObject], $storage->getFromAllLevels());
    }

    public function testDuplicateAddition(): void
    {
        $storage = new ObjectStorage();
        $object = (object)['p' => 'value'];

        $storage->addAtLevel(1, $object);
        $storage->addAtLevel(1, $object);

        $this->assertEquals([$object], $storage->getFromAllLevels());
    }

    public function testAddTwoLevels(): void
    {
        $storage = new ObjectStorage();
        $firstObject = (object)['p' => 'first'];
        $secondObject = (object)['p' => 'second'];
        $storage->addAtLevel(1, $firstObject);
        $storage->addAtLevel(2, $secondObject);

        $this->assertEquals([$firstObject, $secondObject], $storage->getFromAllLevels());
    }
}