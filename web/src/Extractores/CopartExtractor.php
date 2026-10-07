<?php
declare(strict_types=1);

namespace App\Extractores;

use App\Modelos\Captura;

/**
 * Copart (copart.com): los datos vienen de su API interna (lotdetails),
 * que el marcador consulta desde la propia página.
 */
final class CopartExtractor extends Extractor
{
    /** Claves internas de Copart => campo canónico */
    private const CLAVES = [
        'lcy' => 'anio', 'mkn' => 'marca', 'lm' => 'modelo', 'ltd' => 'version',
        'egn' => 'motor', 'cy' => 'cilindros', 'tmtp' => 'transmision', 'drv' => 'traccion',
        'ft' => 'combustible', 'clr' => 'color', 'bstl' => 'carroceria', 'dd' => 'danio_principal',
        'sdd' => 'danio_secundario', 'hk' => 'llaves', 'lcd' => 'estado', 'fv' => 'vin',
        'td' => 'documento', 'yn' => 'ubicacion', 'ln' => 'referencia',
    ];

    public function nombre(): string
    {
        return 'Copart';
    }

    public function clave(): string
    {
        return 'copart';
    }

    public function coincide(string $host): bool
    {
        return str_contains($host, 'copart.');
    }

    protected function dominiosFotos(): array
    {
        return ['copart'];
    }

    protected function specsDesdeJson(Captura $c): array
    {
        foreach ($c->jsons as $json) {
            foreach (self::recorrerJson($json) as $d) {
                if (!isset($d['mkn']) || !(isset($d['lcy']) || isset($d['lm']))) {
                    continue;
                }
                $specs = [];
                foreach (self::CLAVES as $clave => $campo) {
                    $v = $d[$clave] ?? null;
                    if (is_scalar($v) && (string) $v !== '') {
                        $specs[$campo] ??= (string) $v;
                    }
                }
                if (isset($d['orr']) && is_scalar($d['orr'])) {
                    $specs['kilometraje'] = $d['orr'] . ' mi'; // Copart informa millas
                }
                return $specs;
            }
        }
        return [];
    }

    protected function imagenesOficiales(Captura $c): array
    {
        $alta = $completas = [];
        foreach ($c->jsons as $json) {
            foreach (self::recorrerJson($json) as $d) {
                $lista = $d['imagesList'] ?? null;
                if (!is_array($lista)) {
                    continue;
                }
                foreach ((array) ($lista['HIGH_RESOLUTION_IMAGE'] ?? []) as $i) {
                    if (is_array($i) && is_string($i['url'] ?? null)) {
                        $alta[] = $i['url'];
                    }
                }
                foreach ((array) ($lista['FULL_IMAGE'] ?? []) as $i) {
                    if (is_array($i) && is_string($i['url'] ?? null)) {
                        $completas[] = $i['url'];
                    }
                }
            }
        }
        return $alta ?: $completas;
    }

    public function altaResolucion(string $url): string
    {
        return (string) preg_replace('/_(thb|ful|tmb)\.(jpe?g)$/i', '_hrs.$2', $url);
    }
}
