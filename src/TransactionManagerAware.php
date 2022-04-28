<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

interface TransactionManagerAware
{
    public function setTransactionManager(TransactionManager $transactionManager): void;
}