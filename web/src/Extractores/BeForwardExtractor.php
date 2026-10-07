<?php
declare(strict_types=1);

namespace App\Extractores;

/**
 * BE FORWARD (beforward.jp): página simple, la ficha está en tablas.
 */
final class BeForwardExtractor extends Extractor
{
    public function nombre(): string
    {
        return 'BE FORWARD';
    }

    public function clave(): string
    {
        return 'beforward';
    }

    public function coincide(string $host): bool
    {
        return str_contains($host, 'beforward');
    }

    protected function dominiosFotos(): array
    {
        return ['beforward'];
    }

    public function altaResolucion(string $url): string
    {
        return (string) preg_replace('#/(small|medium|thumb|thumbnail|s|m)/#i', '/large/', $url);
    }
}
