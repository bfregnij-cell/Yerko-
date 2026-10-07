<?php
declare(strict_types=1);

namespace App\Core;

use App\Controladores\AccesoController;
use App\Controladores\ExtraccionController;
use App\Controladores\InstalarController;
use App\Controladores\PlantillaController;
use App\Servicios\Autenticacion;
use Throwable;

/**
 * Enrutador: asocia cada ruta (?r=...) con un controlador y una acción.
 */
final class App
{
    /** @var array<string, array{0: class-string<Controlador>, 1: string, 2: bool}> ruta => [controlador, acción, público] */
    private const RUTAS = [
        'inicio'    => [ExtraccionController::class, 'inicio', false],
        'extraer'   => [ExtraccionController::class, 'extraerPorLink', false],
        'recibir'   => [ExtraccionController::class, 'recibirDesdeMarcador', true],
        'procesando' => [ExtraccionController::class, 'procesando', false],
        'ejecutar'  => [ExtraccionController::class, 'ejecutar', false],
        'resultado' => [ExtraccionController::class, 'resultado', false],
        'foto'      => [ExtraccionController::class, 'foto', false],
        'zip'       => [ExtraccionController::class, 'zip', false],
        'instalar'  => [InstalarController::class, 'mostrar', false],
        'plantilla' => [PlantillaController::class, 'editar', false],
        'acceso'    => [AccesoController::class, 'entrar', true],
        'salir'     => [AccesoController::class, 'salir', true],
    ];

    public function __construct(private array $config)
    {
    }

    public function ejecutar(string $ruta): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
        }
        $vista = new Vista(dirname(__DIR__, 2) . '/vistas');

        if (!isset(self::RUTAS[$ruta])) {
            http_response_code(404);
            $vista->mostrar('error', ['mensaje' => 'Página no encontrada.']);
            return;
        }

        [$claseControlador, $accion, $publica] = self::RUTAS[$ruta];
        $auth = new Autenticacion($this->config['clave']);
        if (!$publica && !$auth->autenticado()) {
            header('Location: index.php?r=acceso');
            return;
        }

        try {
            $controlador = new $claseControlador($this->config, $vista, $auth);
            $controlador->$accion();
        } catch (Throwable $e) {
            error_log('[ExtractorAutos] ' . $e);
            http_response_code(500);
            $vista->mostrar('error', ['mensaje' => $e->getMessage()]);
        }
    }
}
