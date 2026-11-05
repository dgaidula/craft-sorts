<?php

/**
 * Autoloading for the engine tests. The engine has no dependencies, so the tests
 * need no Composer install: this maps the plugin’s namespace to src/ and the
 * tests’ own to tests/engine/.
 */

spl_autoload_register(static function (string $class): void {
    $maps = [
        'iceboxind\\sorts\\tests\\engine\\' => __DIR__ . '/',
        'iceboxind\\sorts\\' => dirname(__DIR__, 2) . '/src/',
    ];
    foreach ($maps as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
