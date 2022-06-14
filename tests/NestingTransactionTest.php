<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sbooker\TransactionManager\TransactionHandler;
use Sbooker\TransactionManager\TransactionManager;

final class NestingTransactionTest extends TestCase
{
    /**
     * @covers \Sbooker\TransactionManager\TransactionManager
     */
    public function testTwoNestedTransactionCommit(): void
    {
        $transactionManager = new TransactionManager($this->createTransactionHandler(1, 0));

        $transactionManager->transactional(
            fn() => $transactionManager->transactional(
                function (): void { }
            )
        );
    }

    /**
     * @covers \Sbooker\TransactionManager\TransactionManager
     */
    public function testInnerTransactionRollback(): void
    {
        $transactionManager = new TransactionManager($this->createTransactionHandler(0, 1));

        $this->expectException(\Exception::class);
        $transactionManager->transactional(
            fn() => $transactionManager->transactional(
                function (): void {
                    throw new \Exception();
                }
            )
        );
    }

    /**
     * @covers \Sbooker\TransactionManager\TransactionManager
     */
    public function testInnerSaveTransactionRollback(): void
    {
        $firstEntityId = 1;
        $secondEntityId = 2;
        $firstEntity = (object)['a' => 'a'];
        $secondEntity = (object)['b' => 'b'];

        $spy = new TransactionHandlerSpy();
        $transactionManager = new TransactionManager($spy);

        list($givenFirstEntity, $exception) =
            $transactionManager->transactional(function() use ($transactionManager, $firstEntityId, $secondEntityId) {
                $givenFirstEntity =  $transactionManager->getLocked(\stdClass::class, $firstEntityId);

                $exception = null;
                try {
                    $transactionManager->transactional(
                        function () use ($transactionManager, $secondEntityId): void {
                            $givenSecondEntity = $transactionManager->getLocked(\stdClass::class, $secondEntityId);

                            $transactionManager->save($givenSecondEntity);
                            throw new \Exception('Message');
                        }
                    );
                } catch (\Throwable $e) {
                    $exception = $e;
                }

                return [ $givenFirstEntity, $exception ];
            });

        $this->assertEquals($givenFirstEntity, $firstEntity);
        $this->assertEquals(1, $spy->getBeginCount());
        $this->assertCount(1, $spy->getCommitted());
        $this->assertEquals([$firstEntity], $spy->getCommitted()[0]);
        $this->assertEquals(2, $spy->getGetLockedCount());
        $this->assertEquals('Message', $exception->getMessage());
        $this->assertEquals(0, $spy->getRollbackCount());
        $this->assertEquals([$secondEntity], $spy->getDetached());
    }

    /**
     * @covers \Sbooker\TransactionManager\TransactionManager
     */
    public function testOuterTransactionRollback(): void
    {
        $transactionManager = new TransactionManager($this->createTransactionHandler(0, 1));

        $this->expectException(\Exception::class);
        $transactionManager->transactional(function () use ($transactionManager): void {
            $transactionManager->transactional(function (): void {});
            throw new \Exception();
        });
    }

    /**
     * @covers \Sbooker\TransactionManager\TransactionManager
     */
    public function testCatchInnerTransactionException(): void
    {
        $transactionManager = new TransactionManager($this->createTransactionHandler(1, 0));

        $transactionManager->transactional(function () use ($transactionManager): void {
            try {
                $transactionManager->transactional(function (): void {
                    throw new \Exception();
                });
            } catch (\Exception $e) {
                return;
            }
        });
    }

    private function createTransactionHandler(int $commitCount, int $rollbackCount): TransactionHandler
    {
        /** @var MockObject $mock */
        $mock = $this->createTransactionHandlerMock();

        $mock->expects($this->exactly($commitCount))->method('commit');
        $mock->expects($this->exactly($rollbackCount))->method('rollback');

        return $mock;
    }

    private function createTransactionHandlerMock(): TransactionHandler
    {
        $mock = $this->createMock(TransactionHandler::class);
        $mock->expects($this->once())->method('begin');

        return $mock;
    }
}

final class TransactionHandlerSpy implements TransactionHandler {
    private int $beginCount = 0;
    private array $persisted = [];
    private array $detached = [];
    private array $committed = [];
    private int $rollbackCount = 0;
    private int $clearCount = 0;
    private int $getLockedCount = 0;

    public function begin(): void { $this->beginCount += 1; }

    public function persist(object $entity): void { $this->persisted[] = $entity; }

    public function detach(object $entity): void { $this->detached[] = $entity; }

    public function commit(array $entities): void { $this->committed[] = $entities; }

    public function rollback(): void { $this->rollbackCount += 1; }

    public function clear(): void { $this->clearCount += 1; }

    public function getLocked(string $entityClassName, $entityId): ?object
    {
        $this->getLockedCount += 1;
        if ($entityClassName != \stdClass::class) {
            return null;
        }

        switch ($entityId) {
            case 1: return (object)['a' => 'a'];
            case 2: return (object)['b' => 'b'];
        }

        return null;
    }

    public function getBeginCount(): int
    {
        return $this->beginCount;
    }

    public function getPersisted(): array
    {
        return $this->persisted;
    }

    public function getDetached(): array
    {
        return $this->detached;
    }

    public function getCommitted(): array
    {
        return $this->committed;
    }

    public function getRollbackCount(): int
    {
        return $this->rollbackCount;
    }

    public function getClearCount(): int
    {
        return $this->clearCount;
    }

    public function getGetLockedCount(): int
    {
        return $this->getLockedCount;
    }
}