[Читать на русском](README.md)

# Transaction Manager (`sbooker/transaction-manager`)

[![Latest Version][badge-release]][release]
[![Software License][badge-license]][license]
[![PHP Version][badge-php]][php]
[![Total Downloads][badge-downloads]][downloads]
[![Build Status](https://travis-ci.org/sbooker/transaction-manager.svg?branch=2.x.x)](https://travis-ci.org/sbooker/transaction-manager)
[![codecov](https://codecov.io/gh/sbooker/transaction-manager/branch/2.x.x/graph/badge.svg?token=3uCI9t0M2Q)](https://codecov.io/gh/sbooker/transaction-manager)

An implementation of the Unit of Work pattern that enforces safe and explicit transaction management practices and provides a powerful pre-commit hook mechanism.

## Philosophy and Purpose

The library is designed to work reliably in long-running processes (workers, consumers) and is built on three key principles:

1.  **Explicit Transaction Boundaries.** The library intentionally does not provide public `begin()` and `commit()` methods. The only way to perform an operation is through the `transactional()` closure. This approach makes transaction boundaries absolutely explicit and protects against hard-to-find bugs where `begin()` and `commit()` are scattered across different parts of the code.

2.  **Safe Entity Handling.** The library provides a single method for retrieving entities for modification: `getLocked()`. This is also intentional in order to:
    *   **Force the developer to use locks** (pessimistic or optimistic, depending on the `TransactionHandler` implementation), which prevents data races by default.
    *   **Eliminate the need for repositories** within code that modifies the system's state. Your application code depends only on the `TransactionManager`, making it simpler and cleaner.

3. **Automatic State Management.** After every transactional() operation, the transaction manager completely clears its internal state. This prevents memory leaks and ensures that each transaction starts with a "clean slate," which is critical for a robust worker operation.

## Key Features

*   **Automatic State Clearing:** After every commit or rollback, the transaction manager completely clears its internal state (the Unit of Work) and the state of the underlying handler. This prevents memory leaks and ensures operation isolation in long-running processes.
*   **Explicit Transaction Boundaries:** The `transactional()` method is the only way to perform an atomic operation.
*   **Single Loading Mechanism with Locking:** The `getLocked()` method is the only way to retrieve an entity for modification, forcing the use of locks and preventing concurrent access issues.
*   **Unit of Work Pattern:** Manages a list of modified and new objects and persists them all in a single transaction.
*   **ORM Abstraction:** Your business logic depends only on the `TransactionManager`.
*   **Nested Transaction Support:** Safely call `transactional()` from within another `transactional()` block.
*   **Pre-Commit Hook (`PreCommitEntityProcessor`):** Allows for the creation of powerful tools, such as persisting domain events.

## Installation

```bash
composer require sbooker/transaction-manager
```

## Quick Start

### Step 1: Connect a `TransactionHandler`

The `TransactionManager` requires a "bridge" to your ORM that implements the `TransactionHandler` interface. We provide ready-to-use implementations:

*   **For Doctrine ORM:** `composer require sbooker/doctrine-transaction-handler`
*   **For Yii2 Active Record:** `composer require sbooker/yii2-ar-transaction-handler`

If you use a different ORM, you will need to create your own adapter that implements the `TransactionHandler` interface.

### Step 2: Assemble the `TransactionManager`

```php
// bootstrap.php or your DI container
/** @var Sbooker\DoctrineTransactionHandler\TransactionHandler $transactionHandler */
$transactionManager = new Sbooker\TransactionManager\TransactionManager($transactionHandler);
```

### Step 3: Use It in Your Application Code

#### Creating an Entity Example

```php
// src/Products/Application/Handler.php
final class Handler
{
    private TransactionManager $transactionManager;
    // ...

    public function handle(Command $command): void
    {
        $this->transactionManager->transactional(function () use ($command): void {
            $product = new Product(/* ... */);
            // Register the new entity for persistence
            $this->transactionManager->persist($product);
        });
        // After this block, the manager's internal state is completely cleared.
    }
}
```

#### Modifying an Entity Example

This example demonstrates the full power of the approach. Note the absence of repositories.

```php
// src/Products/Application/Handler.php
final class Handler
{
    private TransactionManager $transactionManager;
    // ...

    public function handle(Command $command): void
    {
        $this->transactionManager->transactional(function () use ($command): void {
            // 1. Get the entity with a lock. This is the only way.
            /** @var Product|null $product */
            $product = $this->transactionManager->getLocked(Product::class, $command->getProductId());

            if (null === $product) {
                throw new \Exception('Product not found.');
            }

            // 2. Execute business logic
            $product->changeName($command->getNewName());

            // 3. NO need to call persist() or save()!
            // An entity retrieved via getLocked() is already managed by the Unit of Work.
        });
        // The Unit of Work is also completely cleared here.
    }
}
```

### Step 4 (Advanced): Adding a `PreCommitProcessor`

Register your processor in the `TransactionManager`'s constructor, and it will be automatically called for all entities (the `new Product` from the first example and `$product` from the second) before the commit.

```php
// bootstrap.php or your DI container
$loggingProcessor = new LoggingProcessor($logger);
$transactionManager = new Sbooker\TransactionManager\TransactionManager(
    $transactionHandler,
    $loggingProcessor
);
```

**Attention!**
```php
$entityId = ...;
$transactionManager->transactional(function () use ($transactionManager, $entityId) {
    $entity = new SomeEntity($entityId);
    $transactionManager->persist($entity);
    
    // Depending on the TransactionHandler implementation, $persistedEntity may be null
    // when fetched in the same transaction where it was persisted.
    $persistedEntity = $transactionManager->getLocked(SomeEntity::class, $entityId);    
});
```   

## License
See [LICENSE][license] file.

[badge-release]: https://img.shields.io/packagist/v/sbooker/transaction-manager.svg?style=flat-square
[badge-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[badge-php]: https://img.shields.io/packagist/php-v/sbooker/transaction-manager.svg?style=flat-square
[badge-downloads]: https://img.shields.io/packagist/dt/sbooker/transaction-manager.svg?style=flat-square

[release]: https://packagist.org/packages/sbooker/transaction-manager
[license]: https://github.com/sbooker/transaction-manager/blob/master/LICENSE
[php]: https://php.net
[downloads]: https://packagist.org/packages/sbooker/transaction-manager


## License
See [LICENSE][license] file.

[badge-release]: https://img.shields.io/packagist/v/sbooker/transaction-manager.svg?style=flat-square
[badge-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[badge-php]: https://img.shields.io/packagist/php-v/sbooker/transaction-manager.svg?style=flat-square
[badge-downloads]: https://img.shields.io/packagist/dt/sbooker/transaction-manager.svg?style=flat-square

[release]: https://img.shields.io/packagist/v/sbooker/transaction-manager
[license]: https://github.com/sbooker/transaction-manager/blob/master/LICENSE
[php]: https://php.net
[downloads]: https://packagist.org/packages/sbooker/transaction-manager