<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sbooker\TransactionManager\ObjectTransactionHandler;
use Sbooker\TransactionManager\TransactionHandler;

/**
 * @covers \Sbooker\TransactionManager\ObjectTransactionHandler
 */
final class ObjectTransactionHandlerTest extends TestCase
{

    public function testPersist(): void
    {
        $entity = new \stdClass();
        $mock = $this->createTransactionHandlerMock();
        $mock->expects($this->once())->method('persist')->with($entity);
        $mock->expects($this->once())->method('commit')->with([$entity]);
        $mock->expects($this->never())->method('rollback');
        $handler = new ObjectTransactionHandler($mock);

        $handler->begin();
        $handler->persist($entity);
        $handler->commit();
    }

    public function testSave(): void
    {
        $entity = new \stdClass();
        $mock = $this->createTransactionHandlerMock();
        $mock->expects($this->never())->method('persist');
        $mock->expects($this->once())->method('commit')->with([$entity]);
        $mock->expects($this->never())->method('rollback');
        $handler = new ObjectTransactionHandler($mock);

        $handler->begin();
        $handler->save($entity);
        $handler->commit();
    }

    public function testRollbackPersist(): void
    {
        $entity = new \stdClass();
        $mock = $this->createTransactionHandlerMock();
        $mock->expects($this->never())->method('persist');
        $mock->expects($this->never())->method('commit');
        $mock->expects($this->once())->method('rollback');
        $handler = new ObjectTransactionHandler($mock);

        $handler->begin();
        $handler->persist($entity);
        $handler->rollback();
    }

    public function testRollbackSave(): void
    {
        $entity = new \stdClass();
        $mock = $this->createTransactionHandlerMock();
        $mock->expects($this->never())->method('persist');
        $mock->expects($this->never())->method('commit');
        $mock->expects($this->once())->method('rollback');
        $handler = new ObjectTransactionHandler($mock);

        $handler->begin();
        $handler->save($entity);
        $handler->rollback();
    }

    public function testSaveNested(): void
    {
        $entityToSave = (object)['p' => 'save'];
        $entityToPersist = (object)['p' => 'persist'];
        $mock = $this->createTransactionHandlerMock();
        $mock->expects($this->once())->method('persist')->with($entityToPersist);
        $mock->expects($this->once())->method('commit')->with([$entityToPersist, $entityToSave]);
        $mock->expects($this->never())->method('rollback');
        $handler = new ObjectTransactionHandler($mock);

        $handler->begin();
        $handler->begin();
        $handler->persist($entityToPersist);
        $handler->commit();
        $handler->save($entityToSave);
        $handler->commit();
    }

    public function testRollbackNestedPersist(): void
    {
        $entityToSave = (object)['p' => 'save'];
        $entityToPersist = (object)['p' => 'persist'];
        $mock = $this->createTransactionHandlerMock();
        $mock->expects($this->never())->method('persist');
        $mock->expects($this->once())->method('commit')->with([$entityToSave]);
        $mock->expects($this->never())->method('rollback');
        $handler = new ObjectTransactionHandler($mock);

        $handler->begin();
        $handler->begin();
        $handler->persist($entityToPersist);
        $handler->rollback();
        $handler->save($entityToSave);
        $handler->commit();
    }


    public function testRollbackNestedSave(): void
    {
        $firstEntity = (object)['p' => 'first'];
        $secondEntity = (object)['p' => 'second'];
        $mock = $this->createTransactionHandlerMock();
        $mock->expects($this->never())->method('persist');
        $mock->expects($this->once())->method('commit')->with([$firstEntity]);
        $mock->expects($this->never())->method('rollback');
        $handler = new ObjectTransactionHandler($mock);

        $handler->begin();
        $handler->begin();
        $handler->save($secondEntity);
        $handler->rollback();
        $handler->save($firstEntity);
        $handler->commit();
    }

    public function testTopRollbackWithNestedPersist(): void
    {
        $entityToSave = (object)['p' => 'save'];
        $entityToPersist = (object)['p' => 'persist'];
        $mock = $this->createTransactionHandlerMock();
        $mock->expects($this->never())->method('persist');
        $mock->expects($this->never())->method('commit');
        $mock->expects($this->once())->method('rollback');
        $handler = new ObjectTransactionHandler($mock);

        $handler->begin();
        $handler->begin();
        $handler->persist($entityToPersist);
        $handler->commit();
        $handler->save($entityToSave);
        $handler->rollback();
    }

    public function testGetLocked(): void
    {
        $entity = (object)['p' => 'value'];
        $entityClass = \stdClass::class;
        $entityId = 'p';
        $mock = $this->createTransactionHandlerMock();
        $mock->expects($this->once())->method('getLocked')->with($entityClass, $entityId)->willReturn($entity);
        $mock->expects($this->once())->method('commit')->with([$entity]);
        $handler = new ObjectTransactionHandler($mock);

        $handler->begin();
        $givenEntity = $handler->getLocked($entityClass, $entityId);
        $handler->commit();

        $this->assertEquals($entity, $givenEntity);
    }

    /**
     * @return TransactionHandler | MockObject
     */
    private function createTransactionHandlerMock(): TransactionHandler
    {
        $mock = $this->createMock(TransactionHandler::class);
        $mock->expects($this->once())->method('begin');

        return $mock;
    }
}