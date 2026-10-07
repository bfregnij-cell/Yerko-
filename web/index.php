<?php
declare(strict_types=1);

/*
 * Extractor de Autos — controlador frontal.
 * Todas las peticiones entran por aquí: index.php?r=<ruta>
 */

spl_autoload_register(function (string $clase): void {
    $prefijo = 'App\\';
    if (strncmp($clase, $prefijo, strlen($prefijo)) !== 0) {
        return;
    }
    $ruta = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require $ruta;
    }
});

$config = require __DIR__ . '/config.php';

(new App\Core\App($config))->ejecutar($_GET['r'] ?? 'inicio');
