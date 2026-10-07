<?php
declare(strict_types=1);

namespace App\Extractores;

/**
 * Cualquier otro sitio: usa solo las reglas comunes.
 */
final class GenericoExtractor extends Extractor
{
    public function nombre(): string
    {
        return 'Sitio genérico';
    }

    public function clave(): string
    {
        return 'generico';
    }

    public function coincide(string $host): bool
    {
        return true;
    }
}
