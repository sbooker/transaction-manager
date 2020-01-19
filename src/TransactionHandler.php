<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

interface TransactionHandler
{
    public function begin(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function clear(): void;
}