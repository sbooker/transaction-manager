<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager\Tests;

use PHPUnit\Framework\TestCase;
use Sbooker\TransactionManager\TransactionHandler;
use Sbooker\TransactionManager\TransactionManager;
use Sbooker\TransactionManager\UniqueConstraintViolation;

/**
 * @covers \Sbooker\TransactionManager\TransactionManager
 * @covers \Sbooker\TransactionManager\ObjectTransactionHandler
 */
final class FailureRecoveryTest extends TestCase
{
    public function testHandlerBeginFailureDoesNotPoisonManager(): void
    {
        $entity = new \stdClass();
        $begins = 0;
        $handler = $this->createMock(TransactionHandler::class);
        $handler->expects($this->exactly(2))->method('begin')
            ->willReturnCallback(function () use (&$begins): void {
                $begins++;
                if ($begins === 1) {
                    throw new \RuntimeException('connection refused');
                }
            });
        $handler->expects($this->once())->method('commit')->with([$entity]);
        $handler->expects($this->never())->method('rollback');

        $manager = new TransactionManager($handler);

        try {
            $manager->transactional(function (): void {});
            $this->fail('A failing begin() must surface to the caller');
        } catch (\RuntimeException $e) {
            $this->assertSame('connection refused', $e->getMessage());
        }

        $manager->transactional(function () use ($manager, $entity): void {
            $manager->save($entity);
        });
    }

    public function testHandlerCommitFailureDoesNotPoisonManager(): void
    {
        $first = (object)['n' => 'first'];
        $second = (object)['n' => 'second'];
        $committed = [];
        $calls = [];
        $handler = $this->createMock(TransactionHandler::class);
        $handler->expects($this->exactly(2))->method('begin')
            ->willReturnCallback(function () use (&$calls): void { $calls[] = 'begin'; });
        $handler->expects($this->once())->method('rollback')
            ->willReturnCallback(function () use (&$calls): void { $calls[] = 'rollback'; });
        $handler->expects($this->exactly(2))->method('clear')
            ->willReturnCallback(function () use (&$calls): void { $calls[] = 'clear'; });
        $handler->expects($this->exactly(2))->method('commit')
            ->willReturnCallback(function (array $entities) use (&$committed, &$calls): void {
                $calls[] = 'commit';
                $committed[] = $entities;
                if (count($committed) === 1) {
                    throw new \RuntimeException('deadlock detected');
                }
            });

        $manager = new TransactionManager($handler);

        try {
            $manager->transactional(function () use ($manager, $first): void {
                $manager->save($first);
            });
            $this->fail('A failing commit() must surface to the caller');
        } catch (\RuntimeException $e) {
            $this->assertSame('deadlock detected', $e->getMessage());
        }

        $manager->transactional(function () use ($manager, $second): void {
            $manager->save($second);
        });

        $this->assertSame([$first], $committed[0]);
        $this->assertSame([$second], $committed[1]);
        $this->assertSame(['begin', 'commit', 'rollback', 'clear', 'begin', 'commit', 'clear'], $calls);
    }

    public function testFailingRollbackDoesNotMaskCommitFailure(): void
    {
        $entity = new \stdClass();
        $handler = $this->createMock(TransactionHandler::class);
        $handler->expects($this->once())->method('commit')
            ->willThrowException(new \RuntimeException('deadlock detected'));
        $handler->expects($this->once())->method('rollback')
            ->willThrowException(new \LogicException('there is no active transaction'));

        $manager = new TransactionManager($handler);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('deadlock detected');

        $manager->transactional(function () use ($manager, $entity): void {
            $manager->save($entity);
        });
    }

    public function testFailingRollbackDoesNotLeaveEntitiesRegistered(): void
    {
        $stale = (object)['n' => 'stale'];
        $fresh = (object)['n' => 'fresh'];
        $committed = [];
        $handler = $this->createMock(TransactionHandler::class);
        $handler->expects($this->once())->method('rollback')
            ->willThrowException(new \LogicException('there is no active transaction'));
        $handler->expects($this->once())->method('commit')
            ->willReturnCallback(function (array $entities) use (&$committed): void { $committed[] = $entities; });

        $manager = new TransactionManager($handler);

        try {
            $manager->transactional(function () use ($manager, $stale): void {
                $manager->save($stale);
                throw new \DomainException('business rule violated');
            });
            $this->fail('A failing closure must surface to the caller');
        } catch (\Throwable $e) {
        }

        $manager->transactional(function () use ($manager, $fresh): void {
            $manager->save($fresh);
        });

        $this->assertSame([$fresh], $committed[0], 'entities of the rolled back transaction must not be resubmitted');
    }

    public function testNestedRollbackKeepsOuterLevelEntitiesRegistered(): void
    {
        $outer = (object)['n' => 'outer'];
        $inner = (object)['n' => 'inner'];
        $committed = [];
        $handler = $this->createMock(TransactionHandler::class);
        $handler->expects($this->once())->method('detach')->with($inner);
        $handler->expects($this->once())->method('commit')
            ->willReturnCallback(function (array $entities) use (&$committed): void { $committed[] = $entities; });

        $manager = new TransactionManager($handler);

        $manager->transactional(function () use ($manager, $outer, $inner): void {
            $manager->save($outer);

            try {
                $manager->transactional(function () use ($manager, $inner): void {
                    $manager->save($inner);
                    throw new \DomainException('inner failed');
                });
            } catch (\DomainException $e) {
            }
        });

        $this->assertSame([$outer], $committed[0]);
    }

    public function testHandlerIsClearedWhenClosureThrows(): void
    {
        $entity = new \stdClass();
        $calls = [];
        $handler = $this->createMock(TransactionHandler::class);
        $handler->expects($this->once())->method('begin')
            ->willReturnCallback(function () use (&$calls): void { $calls[] = 'begin'; });
        $handler->expects($this->once())->method('rollback')
            ->willReturnCallback(function () use (&$calls): void { $calls[] = 'rollback'; });
        $handler->expects($this->once())->method('detach')
            ->willReturnCallback(function () use (&$calls): void { $calls[] = 'detach'; });
        $handler->expects($this->once())->method('clear')
            ->willReturnCallback(function () use (&$calls): void { $calls[] = 'clear'; });
        $handler->expects($this->never())->method('commit');

        $manager = new TransactionManager($handler);

        try {
            $manager->transactional(function () use ($manager, $entity): void {
                $manager->save($entity);
                throw new \DomainException('business rule violated');
            });
            $this->fail('A failing closure must surface to the caller');
        } catch (\DomainException $e) {
        }

        $this->assertSame(['begin', 'rollback', 'detach', 'clear'], $calls);
    }

    public function testNestedRollbackDoesNotClearHandler(): void
    {
        $handler = $this->createMock(TransactionHandler::class);
        $handler->expects($this->once())->method('clear');

        $manager = new TransactionManager($handler);

        $manager->transactional(function () use ($manager): void {
            try {
                $manager->transactional(function (): void {
                    throw new \DomainException('inner failed');
                });
            } catch (\DomainException $e) {
            }
        });
    }

    public function testUniqueConstraintViolationRollsBackAndKeepsManagerUsable(): void
    {
        $first = (object)['n' => 'dup'];
        $second = (object)['n' => 'ok'];
        $committed = [];
        $handler = $this->createMock(TransactionHandler::class);
        $handler->expects($this->exactly(2))->method('begin');
        $handler->expects($this->once())->method('rollback');
        $handler->expects($this->once())->method('detach')->with($first);
        $handler->expects($this->exactly(2))->method('commit')
            ->willReturnCallback(function (array $entities) use (&$committed): void {
                $committed[] = $entities;
                if (count($committed) === 1) {
                    throw new UniqueConstraintViolation();
                }
            });

        $manager = new TransactionManager($handler);

        try {
            $manager->transactional(function () use ($manager, $first): void {
                $manager->save($first);
            });
            $this->fail('UniqueConstraintViolation must surface to the caller');
        } catch (UniqueConstraintViolation $e) {
        }

        $manager->transactional(function () use ($manager, $second): void {
            $manager->save($second);
        });

        $this->assertSame([$second], $committed[1]);
    }
}
