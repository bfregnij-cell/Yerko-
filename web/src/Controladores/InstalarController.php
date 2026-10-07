<?php
declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Servicios\Marcador;

/**
 * Página para instalar el botón en la barra de favoritos.
 */
final class InstalarController extends Controlador
{
    public function mostrar(): void
    {
        $marcador = new Marcador(dirname(__DIR__, 2) . '/assets/marcador.js');
        $this->vista->mostrar('instalar', [
            'titulo' => 'Instalar botón',
            'enlace' => $marcador->enlace($this->urlBase(), $this->auth->tokenMarcador()),
        ]);
    }
}
