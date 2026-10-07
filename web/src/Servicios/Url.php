<?php
declare(strict_types=1);

namespace App\Servicios;

/**
 * Utilidades de URLs.
 */
final class Url
{
    /** Convierte una URL relativa en absoluta respecto de $base. */
    public static function absoluta(string $base, string $relativa): string
    {
        $relativa = trim($relativa);
        if ($relativa === '' || str_starts_with($relativa, 'data:') || str_starts_with($relativa, 'javascript:')) {
            return '';
        }
        if (preg_match('#^https?://#i', $relativa)) {
            return $relativa;
        }
        $b = parse_url($base);
        if (!isset($b['scheme'], $b['host'])) {
            return '';
        }
        $origen = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($relativa, '//')) {
            return $b['scheme'] . ':' . $relativa;
        }
        if (str_starts_with($relativa, '/')) {
            return $origen . $relativa;
        }
        if (str_starts_with($relativa, '?')) {
            return $origen . ($b['path'] ?? '/') . $relativa;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $b['path'] ?? '/');
        $ruta = $dir . $relativa;
        // resolver ./ y ../
        $partes = [];
        foreach (explode('/', $ruta) as $p) {
            if ($p === '..') {
                array_pop($partes);
            } elseif ($p !== '.') {
                $partes[] = $p;
            }
        }
        return $origen . implode('/', $partes);
    }

    public static function normalizarEntrada(string $url): string
    {
        $url = trim($url);
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }
}
