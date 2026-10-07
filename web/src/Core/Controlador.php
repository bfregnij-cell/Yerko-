<?php
declare(strict_types=1);

namespace App\Core;

use App\Servicios\Autenticacion;

/**
 * Base de todos los controladores.
 */
abstract class Controlador
{
    public function __construct(
        protected array $config,
        protected Vista $vista,
        protected Autenticacion $auth,
    ) {
    }

    protected function esPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    protected function redirigir(string $destino): void
    {
        header('Location: ' . $destino, true, 303);
    }

    protected function csrfValido(): bool
    {
        return hash_equals($this->auth->tokenCsrf(), (string) ($_POST['csrf'] ?? ''));
    }

    protected function urlBase(): string
    {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $ruta = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/\\');
        return ($https ? 'https' : 'http') . '://' . $host . $ruta . '/index.php';
    }
}
