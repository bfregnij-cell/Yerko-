<?php
declare(strict_types=1);

namespace App\Extractores;

use App\Modelos\Captura;

/**
 * IAAI (iaai.com): la ficha está en la página como "Etiqueta: valor" y las
 * fotos se piden al servicio vis.iaai.com/resizer con una clave por imagen.
 */
final class IaaiExtractor extends Extractor
{
    private const RESIZER = 'https://vis.iaai.com/resizer';

    public function nombre(): string
    {
        return 'IAAI';
    }

    public function clave(): string
    {
        return 'iaai';
    }

    public function coincide(string $host): bool
    {
        return str_contains($host, 'iaai.');
    }

    protected function dominiosFotos(): array
    {
        return ['iaai'];
    }

    protected function agruparPorCarpeta(): bool
    {
        return false; // todas las fotos pasan por la misma ruta /resizer
    }

    protected function imagenesOficiales(Captura $c): array
    {
        $claves = [];
        foreach ($c->jsons as $json) {
            foreach (self::recorrerJson($json) as $d) {
                $k = $d['K'] ?? null;
                if (is_string($k) && str_contains($k, '~')) {
                    $claves[$k] = true;
                }
            }
        }
        return array_map(
            fn($k) => self::RESIZER . '?' . http_build_query(['imageKeys' => $k, 'width' => 1920, 'height' => 1440]),
            array_keys($claves)
        );
    }

    public function altaResolucion(string $url): string
    {
        $p = parse_url($url);
        if (!str_contains($p['host'] ?? '', 'vis.iaai.com')) {
            return $url;
        }
        parse_str($p['query'] ?? '', $q);
        if (!isset($q['imageKeys'])) {
            return $url;
        }
        $q['width'] = 1920;
        $q['height'] = 1440;
        return 'https://' . $p['host'] . ($p['path'] ?? '') . '?' . http_build_query($q);
    }
}
