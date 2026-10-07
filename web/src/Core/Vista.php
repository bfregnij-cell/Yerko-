<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Renderiza las vistas PHP dentro del layout común.
 */
final class Vista
{
    public function __construct(private string $directorio)
    {
    }

    public function mostrar(string $nombre, array $datos = []): void
    {
        $contenido = $this->renderizar($nombre, $datos);
        echo $this->renderizar('layout', $datos + ['contenido' => $contenido]);
    }

    public function renderizar(string $nombre, array $datos = []): string
    {
        $archivo = $this->directorio . '/' . $nombre . '.php';
        extract($datos, EXTR_SKIP);
        ob_start();
        require $archivo;
        return (string) ob_get_clean();
    }

    /** Escapa texto para HTML. */
    public static function e(?string $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
