# Transaction manager

Abstraction for transaction control on an application tier.

[![Latest Version][badge-release]][release]
[![Software License][badge-license]][license]
[![PHP Version][badge-php]][php]
[![Total Downloads][badge-downloads]][downloads]

## Installation

```bash
composer require sbooker/transaction-manager
```

## Nested transactions

Nested transactions are ignored. 

## Example of usage
```php
use Sbooker\TransactionManager\TransactionHandler;
use Sbooker\TransactionManager\TransactionManager;

$transactionHandler = new class implements TransactionHandler { ... };
$transactionManager = new TransactionManager($transactionHandler);

$transactionManager->transactional(function () {
    // do something what need transaction
});
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