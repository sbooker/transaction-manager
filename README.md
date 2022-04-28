# Transaction manager

Abstraction for transaction control on an application tier.

[![Latest Version][badge-release]][release]
[![Software License][badge-license]][license]
[![PHP Version][badge-php]][php]
[![Total Downloads][badge-downloads]][downloads]
[![Build Status](https://travis-ci.org/sbooker/transaction-manager.svg?branch=2.x.x)](https://travis-ci.org/sbooker/transaction-manager)
[![codecov](https://codecov.io/gh/sbooker/transaction-manager/branch/2.x.x/graph/badge.svg?token=3uCI9t0M2Q)](https://codecov.io/gh/sbooker/transaction-manager)

## Installation

```bash
composer require sbooker/transaction-manager
```

## Example of usage
```php
use Sbooker\TransactionManager\TransactionHandler;
use Sbooker\TransactionManager\TransactionManager;


$transactionManager = new TransactionManager(new class implements TransactionHandler { ... });

class Entity {
    /**
     * @throws \Exception 
     */  
    public function update(): void { ... }
}
```
### Example 1. Create entity
```php
$transactionManager->transactional(function () use ($transactionManager) {
    $transactionManager->persist(new Entity());
});
```
### Example 2. Update entity using only TransactionManager
```php
$transactionManager->transactional(function () use ($transactionManager, $entityId) {
    $entity = $transactionManager->getLocked(Entity::class, $entityId);
    $entity->update(); // if exception throws, transaction will be rolled back
});
```
### Example 3. Update entity using repository
```php
$transactionManager->transactional(function () use ($transactionManager, $entityRepository, $criteria) {
    $entity = $entityRepository->getLocked($criteria);
    $entity->update(); // if exception throws, transaction will be rolled back
    $transactionManager->save($entity);
});
```
### Example 4. Nested transactions support

Usually you need only single transaction to process single command (See examples before).
Usually you do it in Application Layer service (so-called Command Processor).
```php
final class CommandProcessor {
    private TransactionManager $transactionManager;
    ...
    /** @throws \Exception */
    public function update($entityId): void
    {
        $transactionManager->transactional(function () use ($transactionManager, $entityId) {
            $entity = $transactionManager->getLocked(Entity::class, $entityId);
            $entity->update(); 
        });
    }
}
``` 
It's work well while you simple call Application Layer from Presentation Layer synchronously. 
For example using HTTP request and converts it to command in controller. 

But sometimes you need process previously stored command with same domain logic as from HTTP request. 
Of course in this case you want save command execution result. For example, for a next retry if execution fails.
In this case you need nested transaction and outer transaction will not be rolled back.
```php
$commandProcessor = new CommandProcessor($transactionManager);

$transactionManager->transactional(function () use ($transactionManager, $commandId, $commandProcessor) {
    $command = $transactionManager->getLocked(Command::class, $commandId);
   try {
        $commandProcessor->update($command->getEntityId());
        $command->setSuccessExecutionState();
   } catch (\Exception) {
        $command->setFailExecutionState(); 
   }
});
```

**Attention!**
```php
$entityId = ...;
$transactionManager->transactional(function () use ($transactionManager, $entityId) {
    $entity = new SomeEntity($entityId);
    $transactionManager->persist($entity);
    
    // Depends on TransactionHandler implementation $persistedEntity may be null in same transaction with persist
    $persistedEntity = $transactionManager->getLocked($entityId);    
}
```   

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