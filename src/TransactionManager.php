<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

final class TransactionManager
{
    private TransactionHandler $transactionHandler;
    private ?PreCommitEntityProcessor $preCommitEntityProcessor;

    private ?Transaction $topLevelTransaction = null;

    public function __construct(TransactionHandler $transactionHandler, ?PreCommitEntityProcessor $preCommitEntityProcessor = null)
    {
        if ($preCommitEntityProcessor instanceof TransactionManagerAware) {
            $preCommitEntityProcessor->setTransactionManager($this);
        }
        $this->transactionHandler = new IdentityMap($transactionHandler);
        $this->preCommitEntityProcessor = $preCommitEntityProcessor;
    }

    /**
     * @return mixed
     *
     * @throws \Throwable
     */
    public function transactional(callable $fn)
    {
        $transaction = $this->begin();

        try {
            $result = $fn($transaction->isolated());
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollback();
            throw $e;
        }

        return $result;
    }

    private function begin(): Transaction
    {
        if (null === $this->topLevelTransaction) {
            $this->topLevelTransaction = Transaction::begin($this->transactionHandler, $this->preCommitEntityProcessor);

            return $this->topLevelTransaction;
        }

        return $this->topLevelTransaction->beginNested();
    }
}