[Read in English](README_EN.md)

# Менеджер Транзакций (`sbooker/transaction-manager`)

[![Latest Version][badge-release]][release]
[![Software License][badge-license]][license]
[![PHP Version][badge-php]][php]
[![Total Downloads][badge-downloads]][downloads]
[![Build Status](https://travis-ci.org/sbooker/transaction-manager.svg?branch=2.x.x)](https://travis-ci.org/sbooker/transaction-manager)
[![codecov](https://codecov.io/gh/sbooker/transaction-manager/branch/2.x.x/graph/badge.svg?token=3uCI9t0M2Q)](https://codecov.io/gh/sbooker/transaction-manager)

Реализация паттерна Unit of Work, которая навязывает безопасные и явные практики управления транзакциями и предоставляет мощный механизм хуков перед коммитом.

## Философия и назначение

Библиотека спроектирована с учетом работы в долгоживущих процессах (воркерах, консьюмерах) и построена на трех ключевых принципах:

1.  **Явные границы транзакций.** Библиотека намеренно не предоставляет публичные методы `begin()` и `commit()`. Единственный способ выполнить операцию — через замыкание `transactional()`. Такой подход делает границы транзакции абсолютно явными и защищает от трудноуловимых багов, когда `begin()` и `commit()` разнесены по разным частям кода.

2.  **Безопасная работа с сущностями.** Библиотека предоставляет единственный метод для извлечения сущностей с целью их изменения — `getLocked()`. Это также сделано намеренно, чтобы:
    *   **Заставить разработчика использовать блокировки** (пессимистичные или оптимистичные, в зависимости от реализации `TransactionHandler`), что предотвращает гонки данных по умолчанию.
    *   **Устранить необходимость в репозиториях** внутри кода, который изменяет состояние системы. Ваш прикладной код зависит только от `TransactionManager`, что делает его проще и чище.
 
3. **Автоматическое управление состоянием.** После каждой операции transactional() менеджер транзакций полностью очищает свое внутреннее состояние. Это предотвращает утечки памяти и гарантирует, что каждая транзакция начинается "с чистого листа", что критически важно для надежной работы воркеров.

## Ключевые особенности

*   **Автоматическая очистка состояния:** После каждого коммита или отката менеджер транзакций полностью очищает свое внутреннее состояние (Unit of Work) и состояние нижележащего обработчика. Это предотвращает утечки памяти и обеспечивает изоляцию операций в долгоживущих процессах.
*   **Явные границы транзакций:** Метод `transactional()` — единственный способ выполнить атомарную операцию.
*   **Единый механизм загрузки с блокировкой:** Метод `getLocked()` — единственный способ получить сущность для изменения, что заставляет использовать блокировки и предотвращает проблемы параллельного доступа.
*   **Паттерн Unit of Work:** Управляет списком измененных и новых объектов и сохраняет их все в одной транзакции.
*   **Абстракция над ORM:** Ваша бизнес-логика зависит только от `TransactionManager`.
*   **Поддержка вложенных транзакций:** Безопасные вызовы `transactional()` внутри другого `transactional()`.
*   **Хук перед коммитом (`PreCommitEntityProcessor`):** Позволяет создавать мощные инструменты, такие как сохранение доменных событий.

## Установка

```bash
composer require sbooker/transaction-manager
```

## Быстрый старт

### Шаг 1: Подключите `TransactionHandler`

Для работы `TransactionManager` требуется "мост" к вашей ORM. Мы предоставляем готовые реализации:

*   **Для Doctrine ORM:** `composer require sbooker/doctrine-transaction-handler`
*   **Для Yii2 Active Record:** `composer require sbooker/yii2-ar-transaction-handler`

Если вы используете другую ORM, вам нужно будет создать свой адаптер, реализующий интерфейс `TransactionHandler`.

### Шаг 2: Соберите `TransactionManager`

```php
// bootstrap.php или ваш DI-контейнер
/** @var Sbooker\DoctrineTransactionHandler\TransactionHandler $transactionHandler */
$transactionManager = new Sbooker\TransactionManager\TransactionManager($transactionHandler);
```

### Шаг 3: Используйте в прикладном коде

#### Пример создания сущности

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
            // Регистрируем новую сущность для сохранения
            $this->transactionManager->persist($product);
        });
        // После выхода из этого блока, внутреннее состояние менеджера полностью очищено.
    }
}
```

#### Пример изменения сущности

Этот пример демонстрирует всю мощь подхода. Обратите внимание: здесь нет репозиториев.

```php
// src/Products/Application/Handler.php
final class Handler
{
    private TransactionManager $transactionManager;
    // ...

    public function handle(Command $command): void
    {
        $this->transactionManager->transactional(function () use ($command): void {
            // 1. Получаем сущность с блокировкой. Это единственный способ.
            /** @var Product|null $product */
            $product = $this->transactionManager->getLocked(Product::class, $command->getProductId());

            if (null === $product) {
                throw new Exception('Product not found.');
            }

            // 2. Выполняем бизнес-логику
            $product->changeName($command->getNewName());

            // 3. НЕ НУЖНО вызывать persist() или save()!
            // Сущность, полученная через getLocked(), уже находится под управлением Unit of Work.
        });
        // Здесь Unit of Work также полностью очищен.
    }
}
```

### Шаг 4 (Продвинутый): Добавление `PreCommitProcessor`

Зарегистрируйте ваш процессор в конструкторе `TransactionManager`, и он будет автоматически вызываться для всех сущностей (`new Product` из первого примера и `$product` из второго) перед коммитом.

```php
// bootstrap.php или ваш DI-контейнер
$loggingProcessor = new LoggingProcessor($logger);
$transactionManager = new Sbooker\TransactionManager\TransactionManager(
    $transactionHandler,
    $loggingProcessor
);
```

**Внимание!**
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
