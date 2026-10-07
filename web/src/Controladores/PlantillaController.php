<?php
declare(strict_types=1);

namespace App\Controladores;

use App\Core\Controlador;
use App\Servicios\GeneradorPublicacion;
use App\Servicios\NormalizadorSpecs;

/**
 * Permite editar el texto de la publicación desde el navegador.
 */
final class PlantillaController extends Controlador
{
    public static function leer(string $almacen): string
    {
        $archivo = $almacen . '/plantilla.txt';
        $texto = is_file($archivo) ? (string) file_get_contents($archivo) : '';
        return trim($texto) !== '' ? $texto : GeneradorPublicacion::PLANTILLA_POR_DEFECTO;
    }

    public function editar(): void
    {
        $archivo = $this->config['almacen'] . '/plantilla.txt';
        $mensaje = null;
        if ($this->esPost() && $this->csrfValido()) {
            if (isset($_POST['restaurar'])) {
                @unlink($archivo);
                $mensaje = 'Se restauró la plantilla original.';
            } else {
                $texto = str_replace("\r\n", "\n", mb_substr((string) ($_POST['plantilla'] ?? ''), 0, 10000));
                file_put_contents($archivo, $texto);
                $mensaje = 'Plantilla guardada.';
            }
        }
        $plantilla = self::leer($this->config['almacen']);
        $ejemplo = (new GeneradorPublicacion($plantilla))->generar([
            'titulo' => 'Toyota Corolla 2018', 'marca' => 'Toyota', 'modelo' => 'Corolla', 'anio' => '2018',
            'kilometraje' => '87.421 km (54.321 millas)', 'motor' => '1.8L 4 cilindros', 'transmision' => 'Automática',
            'combustible' => 'Bencina', 'color' => 'Plateado', 'danio_principal' => 'Parte delantera',
            'estado' => 'Arranca y anda', 'llaves' => 'Sí', 'fuente' => 'Copart', 'link' => 'https://…',
        ]);
        $this->vista->mostrar('plantilla', [
            'titulo' => 'Editar plantilla',
            'plantilla' => $plantilla,
            'ejemplo' => $ejemplo,
            'campos' => array_merge(['titulo'], NormalizadorSpecs::CAMPOS, ['fuente', 'link']),
            'mensaje' => $mensaje,
            'csrf' => $this->auth->tokenCsrf(),
        ]);
    }
}
