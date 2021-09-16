<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager\Tests;

use PHPUnit\Framework\TestCase;
use Sbooker\TransactionManager\ObjectTransactionHandler;
use Sbooker\TransactionManager\TransactionHandler;
use Sbooker\TransactionManager\TransactionManager;

final class NestingTransactionTest extends TestCase
{
    /**
     * @covers \Sbooker\TransactionManager\TransactionManager
     */
    public function testTwoNestedTransactionWithPersistCommit(): void
    {
        $first = (object)['a' => 'first'];
        $second = (object)['b' => 'second'];
        $transactionManager = new TransactionManager(
            $this->createTransactionHandler(2, [$first, $second], 1, [$first, $second], 0)
        );

        $transactionManager->transactional(function () use ($transactionManager, $first, $second): void {
            $transactionManager->persist($first);
            $transactionManager->transactional(function () use ($transactionManager, $first, $second): void {
                $transactionManager->persist($second);
            });
        });
    }

    /**
     * @covers \Sbooker\TransactionManager\TransactionManager
     */
    public function testTwoNestedTransactionWithOneSaveCommit(): void
    {
        $first = (object)['a' => 'first'];
        $second = (object)['b' => 'second'];
        $transactionManager = new TransactionManager(
            $this->createTransactionHandler(1, [$first], 1, [$first, $second], 0, $second)
        );

        $transactionManager->transactional(function () use ($transactionManager, $first, $second): void {
            $transactionManager->persist($first);
            $transactionManager->transactional(function () use ($transactionManager, $first, $second): void {
                $object = $transactionManager->getLocked(\stdClass::class, 'id');
                $object->b = 'second2';
            });
        });
    }

    /**
     * @covers \Sbooker\TransactionManager\TransactionManager
     */
    public function testInnerTransactionRollback(): void
    {
        $first = (object)['a' => 'first'];
        $second = (object)['b' => 'second'];
        $transactionManager = new TransactionManager(
            $this->createTransactionHandler(0, [], 0, [], 1)
        );

        $this->expectException(\Exception::class);
        $transactionManager->transactional(function () use ($transactionManager, $first, $second): void {
            $transactionManager->persist($first);
            $transactionManager->transactional(function () use ($transactionManager, $first, $second): void {
                $transactionManager->persist($second);
                throw new \Exception();
            });
        });
    }

    /**
     * @covers \Sbooker\TransactionManager\TransactionManager
     */
    public function testOuterTransactionRollback(): void
    {
        $first = (object)['a' => 'first'];
        $second = (object)['b' => 'second'];
        $transactionManager = new TransactionManager(
            $this->createTransactionHandler(0, [], 0, [], 1)
        );

        $this->expectException(\Exception::class);
        $transactionManager->transactional(function () use ($transactionManager, $first, $second): void {
            $transactionManager->persist($first);
            $transactionManager->transactional(function () use ($transactionManager, $first, $second): void {
                $transactionManager->persist($second);
            });
            throw new \Exception();
        });
    }

    /**
     * @covers \Sbooker\TransactionManager\TransactionManager
     */
    public function testCatchInnerTransactionException(): void
    {
        $first = (object)['a' => 'first'];
        $second = (object)['b' => 'second'];
        $transactionManager = new TransactionManager(
            $this->createTransactionHandler(1, [ $first ], 1, [ $first ], 0)
        );

        $transactionManager->transactional(function () use ($transactionManager, $first, $second): void {
            $transactionManager->persist($first);
            try {
                $transactionManager->transactional(function () use ($transactionManager, $first, $second): void {
                    $transactionManager->persist($second);
                    throw new \Exception();
                });
            } catch (\Exception $e) {
                return;
            }
        });
    }

    private function createTransactionHandler(
        int $persistCount,
        array $objectsToPersist,
        int $commitCount,
        array $objectsToCommit,
        int $rollbackCount,
        object $lockedObject = null
    ): TransactionHandler {
        $mock = $this->createMock(TransactionHandler::class);
        $mock->expects($this->once())->method('begin');
        $mock->expects($this->exactly($persistCount))->method('persist')
            ->withConsecutive(...array_map(fn($object) => [$object], $objectsToPersist));
        $mock->expects($this->exactly($commitCount))->method('commit')->with($objectsToCommit);
        $mock->expects($this->exactly($rollbackCount))->method('rollback');
        $mock->method('getLocked')->willReturn($lockedObject);

        return new ObjectTransactionHandler($mock);
    }
}