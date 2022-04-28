<?php

declare(strict_types=1);

namespace Sbooker\TransactionManager;

interface PreCommitEntityProcessor
{
    public function process(object $entity): void;
}