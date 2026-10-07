<?php
declare(strict_types=1);

namespace App\Extractores;

use App\Modelos\Captura;
use App\Servicios\NormalizadorSpecs;
use Generator;

/**
 * Lógica común de extracción. Cada sitio hereda y sobrescribe solo lo que
 * cambia: sus datos internos (JSON), su lista oficial de fotos y cómo pedir
 * cada foto en alta resolución.
 */
abstract class Extractor
{
    private const DESCARTAR = '/logo|icon|sprite|banner|flag|avatar|badge|placeholder|loading|blank|spinner|button|arrow|'
        . 'social|facebook|twitter|whatsapp|youtube|instagram|payment|captcha|pixel|tracking|\.svg/i';
    private const EXTENSIONES = '/\.(jpe?g|png|webp)(\?|$)/i';

    abstract public function nombre(): string;

    abstract public function clave(): string;

    abstract public function coincide(string $host): bool;

    /** Dominios desde donde se aceptan fotos (vacío = cualquiera). */
    protected function dominiosFotos(): array
    {
        return [];
    }

    /** Especificaciones desde los datos internos del sitio. */
    protected function specsDesdeJson(Captura $c): array
    {
        return [];
    }

    /** Lista oficial de fotos del vehículo, si el sitio la entrega. */
    protected function imagenesOficiales(Captura $c): array
    {
        return [];
    }

    /** Versión en alta resolución de una foto. */
    public function altaResolucion(string $url): string
    {
        return $url;
    }

    /** Si las fotos del auto comparten carpeta en el servidor (sirve para descartar "autos similares"). */
    protected function agruparPorCarpeta(): bool
    {
        return true;
    }

    // ------------------------------------------------------------------ especificaciones

    /** @return array<string, string> */
    public function especificaciones(Captura $c, NormalizadorSpecs $n): array
    {
        $crudo = [];
        // Prioridad: datos internos del sitio > tabla de la página > título
        foreach ([$this->specsDesdeJson($c), $n->desdePares($c->pares), $n->desdeTitulo($c->tituloPagina())] as $fuente) {
            foreach ($fuente as $campo => $valor) {
                $crudo[$campo] ??= $valor;
            }
        }
        $datos = $n->normalizar($crudo);
        $titulo = implode(' ', array_filter([$datos['marca'] ?? '', $datos['modelo'] ?? '', $datos['anio'] ?? '']));
        $datos['titulo'] = $titulo !== '' ? $titulo : $c->tituloPagina();
        $datos['fuente'] = $this->nombre();
        $datos['link'] = $c->url;
        return $datos;
    }

    // ------------------------------------------------------------------ fotos

    /** @return array<int, string[]> Por foto: [url_alta_res, url_original] */
    public function candidatas(Captura $c): array
    {
        $urls = $this->imagenesOficiales($c);
        if (!$urls) {
            $urls = array_values(array_unique(array_filter($c->imagenes, fn($u) => $this->esCandidata($u))));
            if ($this->agruparPorCarpeta() && $urls) {
                $conteo = array_count_values(array_map([$this, 'carpetaDe'], $urls));
                arsort($conteo);
                $carpeta = array_key_first($conteo);
                if ($conteo[$carpeta] >= 3) {
                    $urls = array_values(array_filter($urls, fn($u) => $this->carpetaDe($u) === $carpeta));
                }
            }
        }

        $resultado = [];
        $vistas = [];
        foreach ($urls as $u) {
            $alta = $this->altaResolucion($u);
            if (isset($vistas[$alta])) {
                continue;
            }
            $vistas[$alta] = true;
            $resultado[] = $alta === $u ? [$alta] : [$alta, $u];
        }
        return $resultado;
    }

    private function esCandidata(string $url): bool
    {
        if (!preg_match('#^https?://#i', $url) || preg_match(self::DESCARTAR, $url)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $dominios = $this->dominiosFotos();
        if ($dominios && !array_filter($dominios, fn($d) => str_contains($host, $d))) {
            return false;
        }
        return (bool) preg_match(self::EXTENSIONES, $url) || str_contains($url, 'resizer') || str_contains($host, 'image');
    }

    private function carpetaDe(string $url): string
    {
        $p = parse_url($url);
        $ruta = $p['path'] ?? '';
        return ($p['host'] ?? '') . substr($ruta, 0, (int) strrpos($ruta, '/'));
    }

    // ------------------------------------------------------------------ utilidades

    /** Recorre un JSON y entrega cada objeto (arreglo asociativo) que contiene. */
    protected static function recorrerJson(mixed $dato): Generator
    {
        $pila = [$dato];
        while ($pila) {
            $actual = array_pop($pila);
            if (is_array($actual)) {
                if (!array_is_list($actual)) {
                    yield $actual;
                }
                foreach ($actual as $v) {
                    if (is_array($v)) {
                        $pila[] = $v;
                    }
                }
            }
        }
    }
}
