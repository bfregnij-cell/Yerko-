<?php
declare(strict_types=1);

namespace App\Servicios;

use RuntimeException;

/** El sitio no dejó que el servidor leyera la página (anti-robots o error). */
final class SitioBloqueadoException extends RuntimeException
{
}
