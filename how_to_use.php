<?php

/**
 * NepotismFree Autowire - Usage Example
 * 
 * Run this script to see the Autowire component in action.
 * Ensure you have run `composer install` first.
 */

require __DIR__ . '/vendor/autoload.php';

use PedhotDev\NepotismFree\NepotismFree;
use PedhotDev\NepotismFree\Autowire\Autowire;
use PedhotDev\NepotismFree\Autowire\AutowireResolver;
use PedhotDev\NepotismFree\Autowire\Attribute\FromContainer;
use PedhotDev\NepotismFree\Autowire\Attribute\FromEnv;
use PedhotDev\NepotismFree\Autowire\Attribute\FromValue;

// ==========================================
// 1. SETUP THE CONTAINER
// ==========================================

// Create the strict container builder
$builder = NepotismFree::createBuilder();

// Add your strict definitions (e.g. database connections, singletons)
$builder->bind('db.connection', function () {
    // Simulating a database connection object
    return new class {
        public function query() { return "Database Connected!"; }
    };
});

// We can bind other services explicitly if we want
// $builder->bind(UserService::class, ...);

$container = $builder->build();

// ==========================================
// 2. SETUP AUTOWIRE
// ==========================================

// Create the Resolver (The Logic)
$resolver = new AutowireResolver($container);

// Create the Facade ( The Easy Interface)
$autowire = new Autowire($resolver);


// ==========================================
// 3. DEFINE SOME CLASSES (Simulated)
// ==========================================

class UserRepository
{
    public function __construct(
        #[FromContainer('db.connection')]
        public object $db
    ) {}
}

class UserService
{
    public function __construct(
        public UserRepository $repo, // Recursive autowiring (concrete class)
        
        #[FromEnv('API_KEY', 'default-key')]
        public string $apiKey
    ) {}
}

class ReportController
{
    public function __construct(
        public UserService $userService,
        
        #[FromValue(true)]
        public bool $isDebugMode,
        
        // Runtime context parameter
        public int $requestUserId
    ) {}
}


// ==========================================
// 4. RESOLVE!
// ==========================================

echo "Resolving ReportController...\n";

try {
    // We pass 'requestUserId' as runtime context
    $controller = $autowire->resolve(ReportController::class, [
        'requestUserId' => 101
    ]);

    echo "Success!\n";
    echo "Is Debug Mode: " . ($controller->isDebugMode ? 'Yes' : 'No') . "\n";
    echo "API Key: " . $controller->userService->apiKey . "\n";
    echo "DB Status: " . $controller->userService->repo->db->query() . "\n";
    echo "Request User ID: " . $controller->requestUserId . "\n";

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}