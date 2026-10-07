<?php
declare(strict_types=1);

namespace App\Servicios;

use App\Modelos\Captura;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Lee el HTML descargado por el servidor y arma la Captura: pares
 * etiqueta/valor, imágenes, datos JSON incrustados y títulos.
 * (Hace lo mismo que el marcador hace dentro del navegador.)
 */
final class LectorHtml
{
    private const BLOQUEOS = ['pardon our interruption', 'access denied', 'incapsula', 'request unsuccessful',
        'are you a robot', 'just a moment', 'attention required', 'verify you are human', '_incapsula_resource'];

    public function esBloqueo(string $html): bool
    {
        $inicio = mb_strtolower(mb_substr($html, 0, 20000));
        foreach (self::BLOQUEOS as $b) {
            if (str_contains($inicio, $b)) {
                return true;
            }
        }
        return false;
    }

    public function leer(string $html, string $url): Captura
    {
        $doc = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        $xp = new DOMXPath($doc);

        return new Captura(
            url: $url,
            titulo: $this->texto($xp->query('//title')->item(0)),
            h1: $this->texto($xp->query('//h1')->item(0)),
            ogTitulo: $this->meta($xp, 'og:title'),
            pares: $this->pares($xp),
            imagenes: $this->imagenes($xp, $url),
            jsons: $this->jsons($xp),
        );
    }

    private function pares(DOMXPath $xp): array
    {
        $pares = [];
        $agregar = function (string $a, string $b) use (&$pares): void {
            $a = trim($a);
            $b = trim($b);
            if ($a !== '' && $b !== '' && mb_strlen($a) <= 45 && mb_strlen($b) <= 200 && $a !== $b) {
                $pares[] = [$a, $b];
            }
        };

        // Tablas: celdas en pares consecutivos (sirve para 2 y 4 columnas)
        foreach ($xp->query('//tr') as $tr) {
            $celdas = [];
            foreach ($tr->childNodes as $n) {
                if ($n instanceof DOMElement && in_array($n->tagName, ['th', 'td'], true)) {
                    $celdas[] = $this->texto($n);
                }
            }
            for ($i = 0; $i + 1 < count($celdas); $i += 2) {
                $agregar($celdas[$i], $celdas[$i + 1]);
            }
        }
        // Listas de definición
        foreach ($xp->query('//dt') as $dt) {
            $dd = $this->siguienteElemento($dt);
            if ($dd && $dd->tagName === 'dd') {
                $agregar($this->texto($dt), $this->texto($dd));
            }
        }
        // "Etiqueta:" seguida del valor en el elemento hermano
        foreach ($xp->query('//label|//span|//div|//strong|//b|//p|//li|//h4|//h5|//h6') as $el) {
            if ($this->hijosElemento($el) > 2) {
                continue;
            }
            $t = $this->texto($el);
            if ($t === '' || mb_strlen($t) > 45 || !str_ends_with($t, ':')) {
                continue;
            }
            $valor = '';
            if ($sig = $this->siguienteElemento($el)) {
                $valor = $this->texto($sig);
            }
            if ($valor === '' && $el->parentNode instanceof DOMElement) {
                $padre = $this->texto($el->parentNode);
                if (str_starts_with($padre, $t)) {
                    $valor = mb_substr($padre, mb_strlen($t));
                }
            }
            $agregar($t, $valor);
        }
        // "Etiqueta: valor" en un mismo elemento
        foreach ($xp->query('//li|//p|//span|//div') as $el) {
            if ($this->hijosElemento($el) > 3) {
                continue;
            }
            if (preg_match('/^([A-Za-z][A-Za-z #.\/&()-]{1,40}):\s*(.{1,120})$/u', $this->texto($el), $m)) {
                $agregar($m[1] . ':', $m[2]);
            }
        }
        return $pares;
    }

    private function imagenes(DOMXPath $xp, string $base): array
    {
        $urls = [];
        $agregar = function (?string $u) use (&$urls, $base): void {
            if ($u !== null && ($abs = Url::absoluta($base, $u)) !== '') {
                $urls[] = $abs;
            }
        };
        $atributos = ['data-zoom-image', 'data-large', 'data-full', 'data-original', 'data-src', 'data-lazy', 'src'];
        foreach ($xp->query('//img|//source') as $img) {
            /** @var DOMElement $img */
            foreach ($atributos as $a) {
                if ($img->hasAttribute($a)) {
                    $agregar($img->getAttribute($a));
                }
            }
            $agregar($this->mayorDeSrcset($img->getAttribute('srcset') ?: $img->getAttribute('data-srcset')));
        }
        foreach ($xp->query('//a[@href]') as $a) {
            $href = $a->getAttribute('href');
            if (preg_match('/\.(jpe?g|png|webp)(\?|$)/i', $href)) {
                $agregar($href);
            }
        }
        foreach ($xp->query('//meta[@property="og:image"]|//meta[@name="twitter:image"]') as $m) {
            $agregar($m->getAttribute('content'));
        }
        return array_values(array_unique($urls));
    }

    private function jsons(DOMXPath $xp): array
    {
        $jsons = [];
        foreach ($xp->query('//script[@type="application/ld+json" or @type="application/json"]') as $s) {
            $dec = json_decode(trim($s->textContent), true, 64);
            if (is_array($dec)) {
                $jsons[] = $dec;
            }
        }
        return $jsons;
    }

    // ------------------------------------------------------------------ utilidades DOM

    private function texto($nodo): string
    {
        return $nodo ? trim((string) preg_replace('/\s+/u', ' ', $nodo->textContent)) : '';
    }

    private function meta(DOMXPath $xp, string $propiedad): string
    {
        $m = $xp->query('//meta[@property="' . $propiedad . '"]')->item(0);
        return $m instanceof DOMElement ? trim($m->getAttribute('content')) : '';
    }

    private function siguienteElemento(DOMElement $el): ?DOMElement
    {
        for ($n = $el->nextSibling; $n; $n = $n->nextSibling) {
            if ($n instanceof DOMElement) {
                return $n;
            }
        }
        return null;
    }

    private function hijosElemento(DOMElement $el): int
    {
        $n = 0;
        foreach ($el->childNodes as $c) {
            $n += $c instanceof DOMElement ? 1 : 0;
        }
        return $n;
    }

    private function mayorDeSrcset(string $srcset): ?string
    {
        $mejor = null;
        $ancho = -1.0;
        foreach (explode(',', $srcset) as $parte) {
            $p = preg_split('/\s+/', trim($parte));
            if (!$p || $p[0] === '') {
                continue;
            }
            $w = (float) ($p[1] ?? 0);
            if ($w >= $ancho) {
                $ancho = $w;
                $mejor = $p[0];
            }
        }
        return $mejor;
    }
}
