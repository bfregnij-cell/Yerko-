<?php
declare(strict_types=1);

namespace App\Servicios;

/**
 * Arma el texto de la publicación a partir de la plantilla.
 *   - {campo} se reemplaza por el dato.
 *   - Si una línea tiene algún {campo} sin dato, la línea se omite.
 */
final class GeneradorPublicacion
{
    public const PLANTILLA_POR_DEFECTO = <<<TXT
🚗 {titulo}
✨ Versión: {version}

📋 FICHA TÉCNICA
📅 Año: {anio}
🛣️ Kilometraje: {kilometraje}
⚙️ Motor: {motor}
🔩 Cilindros: {cilindros}
🕹️ Transmisión: {transmision}
🚙 Tracción: {traccion}
⛽ Combustible: {combustible}
🎨 Color: {color}
🚘 Carrocería: {carroceria}
🚪 Puertas: {puertas}
💺 Asientos: {asientos}
🔑 Llaves: {llaves}
✅ Estado: {estado}
💥 Daño principal: {danio_principal}
💥 Daño secundario: {danio_secundario}
🔢 VIN / Chasis: {vin}

📩 Escríbeme por interno para más información.
TXT;

    public function __construct(private string $plantilla = self::PLANTILLA_POR_DEFECTO)
    {
    }

    /** @param array<string, string> $datos */
    public function generar(array $datos): string
    {
        $lineas = [];
        foreach (preg_split('/\R/u', $this->plantilla) ?: [] as $linea) {
            preg_match_all('/\{(\w+)\}/', $linea, $m);
            foreach ($m[1] as $campo) {
                if (($datos[$campo] ?? '') === '') {
                    continue 2;
                }
            }
            $lineas[] = rtrim((string) preg_replace_callback('/\{(\w+)\}/', fn($x) => $datos[$x[1]], $linea));
        }
        $texto = (string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lineas));
        return trim($texto) . "\n";
    }
}
