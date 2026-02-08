# Using NepotismFree Autowire: Complete Guide

This guide demonstrates how to use the Autowire facade to resolve complex dependency graphs with Attributes and runtime Context.

## The Scenario

Imagine a **Reporting System** where:
1.  `Database` is a singleton service (Strict Container).
2.  `UserRepository` depends on the Database.
3.  `ReportGenerator` depends on the Repository and an API Key (Environment).
4.  `ReportController` needs the Generator and a specific User ID (Runtime Context).

## 1. Setup & Bindings

First, configure your strict container. Bind only what is truly shared or infrastructural.

```php
use PedhotDev\NepotismFree\Core\NepotismFree;
use PedhotDev\NepotismFree\Autowire\Autowire;
use PedhotDev\NepotismFree\Autowire\AutowireResolver;

// A. Strict Container Setup
$builder = NepotismFree::createBuilder();

// Bind Database as a shared service
$builder->singleton(Database::class, fn() => new Database('localhost'));

$container = $builder->build();

// B. Autowire Wrapper
$resolver = new AutowireResolver($container);
$autowire = new Autowire($resolver);
```

## 2. Definining Classes

Use Autowire attributes to guide resolution where type hints aren't enough.

```php
use PedhotDev\NepotismFree\Autowire\Attribute\FromContainer;
use PedhotDev\NepotismFree\Autowire\Attribute\FromEnv;

class UserRepository
{
    // Recursively resolved. 'Database' is pulled from strict container.
    public function __construct(public Database $db) {}
}

class ReportGenerator
{
    public function __construct(
        public UserRepository $repo,

        #[FromEnv('API_KEY')] // Inject specific env var
        public string $apiKey
    ) {}
}

class ReportController
{
    public function __construct(
        public ReportGenerator $generator,

        // No attribute or type hint in container.
        // Needs to be passed at runtime!
        public int $targetUserId 
    ) {}
}
```

## 3. Resolving with Context

When resolving the root object (`ReportController`), we pass the missing `$targetUserId`.

```php
// C. Resolution
try {
    $controller = $autowire->resolve(ReportController::class, [
        'targetUserId' => 450 // Runtime Context
    ]);
    
    // Success!
    // $controller->targetUserId === 450
    // $controller->generator->apiKey === getenv('API_KEY')
    // $controller->generator->repo->db === $container->get(Database::class)

} catch (\Exception $e) {
    // 4. Deterministic Failure
    // If we forgot 'targetUserId', Autowire throws a clear exception:
    // "Failed to resolve parameter '$targetUserId'..."
    echo $e->getMessage();
}
```

## Resolution Order (Recap)

When `resolve()` is called, Autowire checks sources in this strict priority:

1.  **Attributes**: `#[FromContainer]`, `#[FromEnv]`, `#[FromValue]`
2.  **Context**: Does `$context['paramName']` exist?
3.  **Strict Container**: Is the type explicitly bound?
4.  **Recursive Autowiring**: Can we `new` this class?
5.  **Default Values**: Does the parameter have a default?

If all fail, `ResolutionException` is thrown.