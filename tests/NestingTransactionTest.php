<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager\Tests;

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
        $mock = $this->createMock(TransactionHandler::class);
        $mock->expects($this->once())->method('begin');
        $mock->expects($this->exactly($commitCount))->method('commit');
        $mock->expects($this->exactly($rollbackCount))->method('rollBack');

        return $mock;
    }
}