<?php
declare(strict_types=1);

namespace App\Servicios;

/**
 * Contraseña opcional (config.php → 'clave'). Si está vacía, el uso es libre.
 */
final class Autenticacion
{
    public function __construct(private string $clave)
    {
    }

    public function requiereClave(): bool
    {
        return $this->clave !== '';
    }

    public function autenticado(): bool
    {
        return !$this->requiereClave() || ($_SESSION['autenticado'] ?? false) === true;
    }

    public function entrar(string $clave): bool
    {
        if ($this->requiereClave() && hash_equals($this->clave, $clave)) {
            session_regenerate_id(true);
            $_SESSION['autenticado'] = true;
            return true;
        }
        return false;
    }

    /** Usado cuando el botón del navegador trae un token válido. */
    public function marcarAutenticado(): void
    {
        if (($_SESSION['autenticado'] ?? false) !== true) {
            session_regenerate_id(true);
        }
        $_SESSION['autenticado'] = true;
    }

    public function salir(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function tokenCsrf(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
    }

    /** Token que lleva el botón del navegador (cambia si cambia la contraseña). */
    public function tokenMarcador(): string
    {
        return $this->requiereClave() ? hash_hmac('sha256', 'marcador', $this->clave) : '';
    }

    public function tokenMarcadorValido(string $token): bool
    {
        return !$this->requiereClave() || hash_equals($this->tokenMarcador(), $token);
    }
}
