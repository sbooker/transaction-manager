<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager\Tests;

use PHPUnit\Framework\TestCase;
use Sbooker\TransactionManager\IdentityMap;
use Sbooker\TransactionManager\TransactionHandler;

final class IdentityMapTest extends TestCase
{
    /**
     * @covers \Sbooker\TransactionManager\IdentityMap
     * @dataProvider dataProvider
     */
    public function testGetLocked(string $class, $id, object $entity): void
    {
        $identityMap = new IdentityMap($this->createTransactionHandler($class, $id, $entity));

        $givenEntity = $identityMap->getLocked($class, $id);

        $this->assertEquals($entity, $givenEntity);
    }

    /**
     * @covers \Sbooker\TransactionManager\IdentityMap
     * @dataProvider dataProvider
     */
    public function testDoubleGetLocked(string $class, $id, object $entity): void
    {
        $identityMap = new IdentityMap($this->createTransactionHandler($class, $id, $entity));

        $givenEntity = $identityMap->getLocked($class, $id);
        $this->assertEquals($entity, $givenEntity);

        $givenEntity = $identityMap->getLocked($class, $id);
        $this->assertEquals($entity, $givenEntity);

    }

    /**
     * @covers \Sbooker\TransactionManager\IdentityMap
     * @dataProvider dataProvider
     */
    public function testClear(string $class, $id, object $entity): void
    {
        $identityMap = new IdentityMap($this->createTransactionHandler($class, $id, $entity, 2 ,1));

        $givenEntity = $identityMap->getLocked($class, $id);
        $this->assertEquals($entity, $givenEntity);
        $givenEntity = $identityMap->getLocked($class, $id);
        $this->assertEquals($entity, $givenEntity);
        $identityMap->clear();
        $givenEntity = $identityMap->getLocked($class, $id);
        $this->assertEquals($entity, $givenEntity);
    }

    /**
     * @covers \Sbooker\TransactionManager\IdentityMap
     */
    public function testClearReleasesEntityIdentifiers(): void
    {
        $entity = new SomeEntity();
        $identityMap = new IdentityMap($this->createTransactionHandler(SomeEntity::class, 1, $entity, 1, 1));

        $identityMap->getLocked(SomeEntity::class, 1);
        $identityMap->clear();

        $this->assertSame([], $this->readEntityIdentifiers($identityMap));
    }

    private function readEntityIdentifiers(IdentityMap $identityMap): array
    {
        $property = new \ReflectionProperty(IdentityMap::class, 'entityIdentifiers');

        return $property->getValue($identityMap);
    }

    public function dataProvider(): array
    {
        return [
            [ SomeEntity::class, 1, new SomeEntity(), ],
            [ SomeEntity::class, 'a', new SomeEntity(), ],
            [ SomeEntity::class, new EntityId(1), new SomeEntity(), ],
            [ SomeEntity::class, new EntityId('a'), new SomeEntity(), ],
            [ SomeEntity::class, [1, 2], new SomeEntity(), ],
            [ SomeEntity::class, [1, 'a'], new SomeEntity(), ],
            [ SomeEntity::class, ['b', 'a'], new SomeEntity(), ],
            [ SomeEntity::class, ['b', new EntityId('a')], new SomeEntity(), ],
            [ SomeEntity::class,  [KeyString::PartOne, KeyString::PartTwo], new SomeEntity(), ],
            [ SomeEntity::class,  ['part1', KeyString::PartTwo], new SomeEntity(), ],
            [ SomeEntity::class,  [KeyString::PartOne, 'part2'], new SomeEntity(), ],
            [ SomeEntity::class,  KeyString::PartOne, new SomeEntity(), ],
            [ SomeEntity::class,  [1, KeyInteger::PartTwo], new SomeEntity(), ],
            [ SomeEntity::class,  [KeyInteger::PartOne, 2], new SomeEntity(), ],
            [ SomeEntity::class,  KeyInteger::PartOne, new SomeEntity(), ],
        ];
    }

    private function createTransactionHandler(string $class, $id, object $entity, int $getLockedCalls = 1, int $clearCalls = 0): TransactionHandler
    {
        $mock = $this->createMock(TransactionHandler::class);
        $mock->expects($this->exactly($getLockedCalls))->method('getLocked')->with($class, $id)->willReturn($entity);
        $mock->expects($this->never())->method('begin');
        $mock->expects($this->never())->method('commit');
        $mock->expects($this->never())->method('rollback');
        $mock->expects($this->never())->method('persist');
        $mock->expects($this->exactly($clearCalls))->method('clear');

        return $mock;
    }
}

final class SomeEntity {

}

final class EntityId {
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return (string)$this->value;
    }
}

enum KeyString: string
{
    Case PartOne = 'part1';
    case PartTwo = 'part2';
}

enum KeyInteger: int
{
    case PartOne = 1;
    case PartTwo = 2;
}