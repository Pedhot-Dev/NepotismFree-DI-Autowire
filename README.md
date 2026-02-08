# NepotismFree Autowire

**NepotismFree Autowire** is an *optional* reflection-based resolution layer for the strict [NepotismFree-DI](https://github.com/pedhot-dev/nepotismfree-di) container.

It allows you to resolve concrete classes and inject dependencies automatically using attributes, without compromising the strict, nepotism-free philosophy of the core container.

## Philosophy

1.  **Strict DI Stays Strict**: This library does *not* modify the core container. The container remains immutable, explicit, and dumb (in a good way).
2.  **Autowire is NOT a Container**: It is a *resolver*. It uses the container to fetch defined services but instantiates undefined concrete classes on the fly.
3.  **Opt-In Complexity**: Reflection is only used when you explicitly ask for it via the Autowire facade.

## Installation

```bash
composer require pedhot-dev/nepotismfree-autowire
```

## Basic Usage

Wrap your strict container with the Autowire resolver.

```php
use PedhotDev\NepotismFree\Core\NepotismFree;
use PedhotDev\NepotismFree\Autowire\Autowire;
use PedhotDev\NepotismFree\Autowire\AutowireResolver;

// 1. Build your strict container (Definitions & Singletons)
$builder = NepotismFree::createBuilder();
$builder->bind(Database::class, fn() => new Database(...));
$container = $builder->build();

// 2. Wrap it with Autowire
$resolver = new AutowireResolver($container);
$autowire = new Autowire($resolver);

// 3. Resolve!
// 'UserController' is NOT in the container, but Autowire creates it 
// and injects 'Database' from the strict container.
$controller = $autowire->resolve(UserController::class);
```

## Attributes

You can control injection logic using PHP Attributes.

| Attribute | Description | Example |
| :--- | :--- | :--- |
| `#[FromContainer]` | Inject a specific service ID from the strict container. | `#[FromContainer('db.read')]` |
| `#[FromEnv]` | Inject a value from environment variables. | `#[FromEnv('API_KEY')]` |
| `#[FromValue]` | Inject a raw/scalar value directly. | `#[FromValue(100)]` |

## Architecture: Facade vs. Resolver

We separate **Login** from **Ergonomics**.

-   **`AutowireResolver`**: The brain. Contains all reflection logic, attribute parsing, and recursion.
-   **`Autowire`**: The face. A simple facade that forwards calls to the resolver.

You will almost always use the `Autowire` facade in your application.

## What this library does NOT do

*   **It does NOT auto-register services.**
    *   Resolved instances are *not* stored back into the container.
    *   If you resolve `MyService` twice, you get two new instances (unless it's explicitly bound as a singleton in the underlying container).
*   **It does NOT implement `ContainerInterface`.**
    *   It is a tool for *resolving*, not for *storing*.
*   **It does NOT support "magic" binding.**
    *   There is no `bind()` method on the Autowire layer. Bindings belong in the strict container. Autowire just reads them.

## When to use?

*   **Use Strict Container** for: Core infrastructure, specific implementations of interfaces, singletons, and reusable services.
*   **Use Autowire** for: Controllers, Jobs, Commands, and "leaf" nodes of your application that have many dependencies but don't need to be shared.
