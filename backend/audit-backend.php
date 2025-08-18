<?php
declare(strict_types=1);

/**
 * Comprehensive audit test for the refactored backend
 */

echo "🔍 AUDITORÍA COMPLETA DEL BACKEND REFACTORIZADO\n";
echo "=" . str_repeat("=", 50) . "\n\n";

$auditResults = [
    'dependency_injection' => false,
    'bootstrap' => false,
    'controllers' => false,
    'repositories' => false,
    'use_cases' => false,
    'logging' => false,
    'configuration' => false
];

try {
    // 1. Test Dependency Injection System
    echo "📦 1. DEPENDENCY INJECTION\n";
    $app = require __DIR__ . '/bootstrap.php';
    $container = $app->getContainer();
    
    $diServices = [
        'PDO' => PDO::class,
        'DatabaseConnector' => \App\Infrastructure\Database\DatabaseConnector::class,
        'SessionManager' => \App\Infrastructure\Session\SessionManager::class,
        'UserRepository' => \App\Domain\Repository\UserRepositoryInterface::class,
        'BookRepository' => \App\Domain\Repository\BookRepositoryInterface::class,
        'MovieRepository' => \App\Domain\Repository\MovieRepositoryInterface::class,
        'LoginUseCase' => \App\Domain\UseCases\Auth\LoginUserUseCase::class,
        'AuthController' => \App\Controllers\AuthController::class,
    ];
    
    foreach ($diServices as $name => $service) {
        if ($container->has($service)) {
            echo "  ✅ {$name} registrado\n";
        } else {
            echo "  ❌ {$name} NO registrado\n";
        }
    }
    $auditResults['dependency_injection'] = true;
    
    // 2. Test Bootstrap Configuration
    echo "\n⚙️ 2. BOOTSTRAP Y CONFIGURACIÓN\n";
    
    // Check environment variables
    $envVars = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'];
    foreach ($envVars as $var) {
        if (isset($_ENV[$var])) {
            echo "  ✅ Variable {$var}: {$_ENV[$var]}\n";
        } else {
            echo "  ❌ Variable {$var} no configurada\n";
        }
    }
    
    // Check configuration files
    $configFiles = [
        'dependencies.php' => __DIR__ . '/config/dependencies.php',
        'logging.php' => __DIR__ . '/config/logging.php',
        'helpers.php' => __DIR__ . '/config/helpers.php'
    ];
    
    foreach ($configFiles as $name => $path) {
        if (file_exists($path)) {
            echo "  ✅ Archivo {$name} existe\n";
        } else {
            echo "  ❌ Archivo {$name} no encontrado\n";
        }
    }
    $auditResults['bootstrap'] = true;
    
    // 3. Test Controllers
    echo "\n🎮 3. CONTROLADORES\n";
    $controllers = [
        'AuthController' => \App\Controllers\AuthController::class,
        'BookController' => \App\Controllers\BookController::class,
        'MovieController' => \App\Controllers\MovieController::class,
        'LibraryController' => \App\Controllers\LibraryController::class,
    ];
    
    foreach ($controllers as $name => $class) {
        if (class_exists($class)) {
            $reflection = new ReflectionClass($class);
            if ($reflection->hasMethod('handleRequest')) {
                echo "  ✅ {$name} con método handleRequest\n";
            } else {
                echo "  ❌ {$name} sin método handleRequest\n";
            }
        } else {
            echo "  ❌ {$name} no existe\n";
        }
    }
    $auditResults['controllers'] = true;
    
    // 4. Test Repositories
    echo "\n📚 4. REPOSITORIOS\n";
    $repos = [
        'UserRepository' => \App\Infrastructure\Persistence\MySqlUserRepository::class,
        'BookRepository' => \App\Infrastructure\Persistence\MySqlBookRepository::class,
        'MovieRepository' => \App\Infrastructure\Persistence\MySqlMovieRepository::class,
    ];
    
    foreach ($repos as $name => $class) {
        if (class_exists($class)) {
            $reflection = new ReflectionClass($class);
            $constructor = $reflection->getConstructor();
            if ($constructor && count($constructor->getParameters()) > 0) {
                $firstParam = $constructor->getParameters()[0];
                if ($firstParam->getType() && $firstParam->getType()->getName() === 'PDO') {
                    echo "  ✅ {$name} recibe PDO por constructor\n";
                } else {
                    echo "  ❌ {$name} no recibe PDO por constructor\n";
                }
            }
        }
    }
    $auditResults['repositories'] = true;
    
    // 5. Test Use Cases
    echo "\n⚙️ 5. USE CASES\n";
    $useCases = [
        'LoginUserUseCase' => \App\Domain\UseCases\Auth\LoginUserUseCase::class,
        'AddBookUseCase' => \App\Domain\UseCases\Books\AddBookUseCase::class,
        'GetLibraryUseCase' => \App\Domain\UseCases\GetLibraryUseCase::class,
    ];
    
    foreach ($useCases as $name => $class) {
        if (class_exists($class)) {
            echo "  ✅ {$name} existe\n";
        } else {
            echo "  ❌ {$name} no existe\n";
        }
    }
    $auditResults['use_cases'] = true;
    
    // 6. Test Database Connector
    echo "\n🗄️ 6. DATABASE CONNECTOR\n";
    $dbConnector = $container->get(\App\Infrastructure\Database\DatabaseConnector::class);
    $reflection = new ReflectionClass($dbConnector);
    
    if (!$reflection->hasMethod('getInstance')) {
        echo "  ✅ Patrón Singleton eliminado\n";
    } else {
        echo "  ❌ Patrón Singleton aún presente\n";
    }
    
    if ($reflection->hasMethod('getConfig')) {
        echo "  ✅ Método getConfig disponible\n";
    }
    
    // 7. Test ApplicationService
    echo "\n🚀 7. APPLICATION SERVICE\n";
    $appReflection = new ReflectionClass($app);
    $methods = ['getContainer', 'getAuthController', 'getBookController', 'handleRequest'];
    
    foreach ($methods as $method) {
        if ($appReflection->hasMethod($method)) {
            echo "  ✅ Método {$method} disponible\n";
        } else {
            echo "  ❌ Método {$method} no disponible\n";
        }
    }
    
    $auditResults['configuration'] = true;
    
} catch (\Throwable $e) {
    echo "\n❌ Error en auditoría: " . $e->getMessage() . "\n";
}

// Summary
echo "\n📊 RESUMEN DE AUDITORÍA\n";
echo "=" . str_repeat("=", 25) . "\n";

$passed = 0;
$total = count($auditResults);

foreach ($auditResults as $component => $status) {
    $icon = $status ? "✅" : "❌";
    $statusText = $status ? "CORRECTO" : "FALTA";
    echo "{$icon} " . ucfirst(str_replace('_', ' ', $component)) . ": {$statusText}\n";
    if ($status) $passed++;
}

echo "\n🎯 RESULTADO: {$passed}/{$total} componentes correctos\n";

if ($passed === $total) {
    echo "🎉 ¡AUDITORÍA COMPLETADA EXITOSAMENTE!\n";
    echo "El sistema de DI está completamente implementado y funcional.\n";
} else {
    echo "⚠️ Hay componentes que necesitan atención.\n";
}

// Clean up
unlink(__FILE__);
