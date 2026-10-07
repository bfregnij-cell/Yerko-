<?php
declare(strict_types=1);

namespace App\Modelos;

/**
 * Contenido crudo de la página de un auto, venga del servidor (cURL) o del
 * botón del navegador (marcador).
 */
final class Captura
{
    /**
     * @param array<int, array{0: string, 1: string}> $pares   Pares etiqueta/valor encontrados en la página
     * @param string[]                                $imagenes URLs de imágenes encontradas
     * @param array<int, mixed>                       $jsons    Datos JSON (incrustados o de la API del sitio)
     */
    public function __construct(
        public readonly string $url,
        public readonly string $titulo = '',
        public readonly string $h1 = '',
        public readonly string $ogTitulo = '',
        public readonly array $pares = [],
        public readonly array $imagenes = [],
        public readonly array $jsons = [],
    ) {
    }

    /** Construye la captura desde el JSON que envía el marcador. Todo es dato no confiable. */
    public static function desdeMarcador(array $d): self
    {
        $texto = static fn($v, int $max = 500): string => mb_substr(is_string($v) ? trim($v) : '', 0, $max);

        $pares = [];
        foreach (array_slice((array) ($d['pares'] ?? []), 0, 2000) as $p) {
            if (is_array($p) && isset($p[0], $p[1]) && is_string($p[0]) && is_string($p[1])) {
                $pares[] = [$texto($p[0], 60), $texto($p[1], 300)];
            }
        }

        $imagenes = [];
        foreach (array_slice((array) ($d['imagenes'] ?? []), 0, 3000) as $u) {
            if (is_string($u) && preg_match('#^https?://#i', $u) && strlen($u) < 2000) {
                $imagenes[] = $u;
            }
        }

        $jsons = [];
        foreach (array_slice((array) ($d['jsons'] ?? []), 0, 50) as $j) {
            $dec = is_string($j) ? json_decode($j, true, 64) : (is_array($j) ? $j : null);
            if (is_array($dec)) {
                $jsons[] = $dec;
            }
        }

        return new self(
            url: $texto($d['url'] ?? '', 2000),
            titulo: $texto($d['titulo'] ?? '', 300),
            h1: $texto($d['h1'] ?? '', 300),
            ogTitulo: $texto($d['og_titulo'] ?? '', 300),
            pares: $pares,
            imagenes: array_values(array_unique($imagenes)),
            jsons: $jsons,
        );
    }

    /** Texto que mejor describe al auto según la página. */
    public function tituloPagina(): string
    {
        foreach ([$this->h1, $this->ogTitulo, $this->titulo] as $t) {
            if (trim($t) !== '') {
                return trim($t);
            }
        }
        return '';
    }
}
