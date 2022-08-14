<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sbooker\TransactionManager\EntityManager;
use Sbooker\TransactionManager\TransactionHandler;
use Sbooker\TransactionManager\TransactionManager;

/**
 * @covers \Sbooker\TransactionManager\TransactionManager
 */
final class NestingTransactionTest extends TestCase
{
    public function testNestedTransactionCommit(): void
    {
        $transactionManager = new TransactionManager($this->createTransactionHandler(1, 0));

        $transactionManager->transactional(
            fn() => $transactionManager->transactional(
                function (): void { }
            )
        );
    }

    public function testTwoNestedTransactionCommit(): void
    {
        $firstEntityId = 1;
        $secondEntityId = 2;
        $firstEntity = (object)['a' => 'a'];
        $secondEntity = (object)['b' => 'b'];

        $spy = new TransactionHandlerSpy();
        $transactionManager = new TransactionManager($spy);

        $transactionManager->transactional(function (EntityManager $em) use ($transactionManager, $firstEntityId, $secondEntityId) {
            $transactionManager->transactional(function (EntityManager $em) use ($firstEntityId) {
                $em->getLocked(\stdClass::class, $firstEntityId);
            });
            $transactionManager->transactional(function (EntityManager $em) use ($secondEntityId) {
                $em->getLocked(\stdClass::class, $secondEntityId);
            });
        });

        $this->assertEquals(1, $spy->getBeginCount());
        $this->assertEquals(1, $spy->getCommitCount());
        $this->assertEquals([$firstEntity, $secondEntity], $spy->getSaved());
        $this->assertEquals(2, $spy->getGetLockedCount());
        $this->assertEquals(0, $spy->getRollbackCount());
        $this->assertEquals([], $spy->getDetached());
    }

    public function testTwoNestedTransaction_SecondTrows(): void
    {
        $firstEntityId = 1;
        $secondEntityId = 2;
        $firstEntity = (object)['a' => 'a'];
        $secondEntity = (object)['b' => 'b'];

        $spy = new TransactionHandlerSpy();
        $transactionManager = new TransactionManager($spy);

        try {
            $transactionManager->transactional(function (EntityManager $em) use ($transactionManager, $firstEntityId, $secondEntityId) {
                $transactionManager->transactional(function (EntityManager $em) use ($firstEntityId) {
                    $em->getLocked(\stdClass::class, $firstEntityId);
                });
                $transactionManager->transactional(function (EntityManager $em) use ($secondEntityId) {
                    $em->getLocked(\stdClass::class, $secondEntityId);
                    throw new \Exception();
                });
                $this->fail();
            });
        } catch (\Exception $exception) {
        }

        $this->assertEquals(1, $spy->getBeginCount());
        $this->assertEquals(0, $spy->getCommitCount());
        $this->assertEquals([], $spy->getSaved());
        $this->assertEquals(2, $spy->getGetLockedCount());
        $this->assertEquals(1, $spy->getRollbackCount());
        $this->assertEquals([$secondEntity, $firstEntity], $spy->getDetached());
    }

    public function testTwoNestedTransaction_SecondTrowsAndCatch(): void
    {
        $firstEntityId = 1;
        $secondEntityId = 2;
        $firstEntity = (object)['a' => 'a'];
        $secondEntity = (object)['b' => 'b'];

        $spy = new TransactionHandlerSpy();
        $transactionManager = new TransactionManager($spy);

        $transactionManager->transactional(function (EntityManager $em) use ($transactionManager, $firstEntityId, $secondEntityId) {
            $transactionManager->transactional(function (EntityManager $em) use ($firstEntityId) {
                $em->getLocked(\stdClass::class, $firstEntityId);
            });
            try {
                $transactionManager->transactional(function (EntityManager $em) use ($secondEntityId) {
                    $em->getLocked(\stdClass::class, $secondEntityId);
                    throw new \Exception();
                });
                $this->fail();
            } catch (\Exception $exception) {

            }


        });

        $this->assertEquals(1, $spy->getBeginCount());
        $this->assertEquals(1, $spy->getCommitCount());
        $this->assertEquals([$firstEntity], $spy->getSaved());
        $this->assertEquals(2, $spy->getGetLockedCount());
        $this->assertEquals(0, $spy->getRollbackCount());
        $this->assertEquals([$secondEntity], $spy->getDetached());
    }

    public function testTransactionRollback(): void
    {
        $transactionManager = new TransactionManager($this->createTransactionHandler(0, 1));

        $this->expectException(\Exception::class);
        $transactionManager->transactional(
            function ()  {
                throw new \Exception();
            }
        );
    }

    public function testInnerTransactionRollback(): void
    {
        $transactionManager = new TransactionManager($this->createTransactionHandler(0, 1));

        $this->expectException(\Exception::class);
        $transactionManager->transactional(
            function () use ($transactionManager) {
                $transactionManager->transactional(
                    function () {
                        throw new \Exception();
                    });
            }
        );
    }

    public function testInnerSaveTransactionRollback(): void
    {
        $firstEntityId = 1;
        $secondEntityId = 2;
        $firstEntity = (object)['a' => 'a'];
        $secondEntity = (object)['b' => 'b'];

        $spy = new TransactionHandlerSpy();
        $transactionManager = new TransactionManager($spy);

        list($givenFirstEntity, $exception) =
            $transactionManager->transactional(function(EntityManager $em) use ($transactionManager, $firstEntityId, $secondEntityId) {
                $givenFirstEntity =  $em->getLocked(\stdClass::class, $firstEntityId);

                $exception = null;
                try {
                    $transactionManager->transactional(
                        function (EntityManager $em) use ($transactionManager, $secondEntityId): void {
                            $givenSecondEntity = $em->getLocked(\stdClass::class, $secondEntityId);

                            $em->save($givenSecondEntity);
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
        $this->assertEquals(1, $spy->getCommitCount());
        $this->assertEquals([$firstEntity], $spy->getSaved());
        $this->assertEquals(2, $spy->getGetLockedCount());
        $this->assertEquals('Message', $exception->getMessage());
        $this->assertEquals(0, $spy->getRollbackCount());
        $this->assertEquals([$secondEntity], $spy->getDetached());
    }

    public function testOuterTransactionRollback(): void
    {
        $transactionManager = new TransactionManager($this->createTransactionHandler(0, 1));

        $this->expectException(\Exception::class);
        $transactionManager->transactional(function () use ($transactionManager): void {
            $transactionManager->transactional(function (): void {});
            throw new \Exception();
        });
    }

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
    private array $saved = [];
    private array $detached = [];
    private int $rollbackCount = 0;
    private int $commitCount = 0;
    private int $clearCount = 0;
    private int $getLockedCount = 0;

    public function begin(): void { $this->beginCount += 1; }

    public function persist(object $entity): void { $this->persisted[] = $entity; }

    public function detach(object $entity): void { $this->detached[] = $entity; }

    public function commit(): void { $this->commitCount += 1; }

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

    public function getCommitCount(): int
    {
        return $this->commitCount;
    }

    public function save(object $entity): void
    {
        $this->saved[] = $entity;
    }

    public function getSaved(): array
    {
        return $this->saved;
    }
}