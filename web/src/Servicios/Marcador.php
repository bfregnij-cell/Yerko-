<?php
declare(strict_types=1);

namespace App\Servicios;

/**
 * Genera el código del botón para la barra de favoritos (bookmarklet).
 * El código fuente está en assets/marcador.js.
 */
final class Marcador
{
    public function __construct(private string $archivoJs)
    {
    }

    public function enlace(string $destino, string $token): string
    {
        return 'javascript:' . rawurlencode($this->codigo($destino, $token));
    }

    public function codigo(string $destino, string $token): string
    {
        $js = (string) file_get_contents($this->archivoJs);
        $js = (string) preg_replace('#/\*.*?\*/#s', '', $js);
        $lineas = array_filter(array_map('trim', explode("\n", $js)), fn($l) => $l !== '');
        return strtr(implode("\n", $lineas), [
            '__DESTINO__' => addslashes($destino),
            '__TOKEN__' => addslashes($token),
        ]);
    }
}
