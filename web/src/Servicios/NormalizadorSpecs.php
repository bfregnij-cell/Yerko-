<?php
declare(strict_types=1);

namespace App\Servicios;

/**
 * Convierte etiquetas de cualquier sitio en campos fijos, limpia los valores
 * y los traduce al español.
 */
final class NormalizadorSpecs
{
    public const CAMPOS = [
        'marca', 'modelo', 'anio', 'version', 'kilometraje', 'motor', 'cilindros',
        'transmision', 'traccion', 'combustible', 'color', 'carroceria', 'puertas',
        'asientos', 'llaves', 'estado', 'danio_principal', 'danio_secundario',
        'vin', 'documento', 'ubicacion', 'precio', 'referencia',
    ];

    /** Campo => etiquetas posibles (minúsculas, sin acentos ni ":") */
    private const SINONIMOS = [
        'marca' => ['make', 'marca', 'maker', 'manufacturer'],
        'modelo' => ['model', 'modelo'],
        'anio' => ['year', 'model year', 'ano', 'registration year', 'registration year/month',
            'manufacture year', 'manufacture year/month', 'reg. year', 'reg year', 'registrationyear/month'],
        'version' => ['series', 'trim', 'grade', 'version', 'sub model', 'version/class'],
        'kilometraje' => ['mileage', 'odometer', 'kilometraje', 'km', 'odometer reading', 'odo'],
        'motor' => ['engine', 'engine type', 'engine size', 'engine capacity', 'displacement', 'motor', 'cc'],
        'cilindros' => ['cylinders', 'cylinder', 'cilindros'],
        'transmision' => ['transmission', 'trans', 'transmision', 'gearbox'],
        'traccion' => ['drive', 'drive type', 'drivetrain', 'drive line type', 'drive line', 'traccion', 'drive train'],
        'combustible' => ['fuel', 'fuel type', 'combustible'],
        'color' => ['color', 'colour', 'exterior color', 'ext color', 'exterior colour', 'ext. color'],
        'carroceria' => ['body style', 'body type', 'body', 'carroceria', 'type'],
        'puertas' => ['doors', 'door', 'puertas'],
        'asientos' => ['seats', 'seating capacity', 'asientos', 'seating'],
        'llaves' => ['keys', 'key', 'keys available', 'llaves'],
        'estado' => ['highlights', 'start code', 'run & drive', 'run and drive', 'condition', 'vehicle condition'],
        'danio_principal' => ['primary damage', 'damage', 'loss', 'dano principal'],
        'danio_secundario' => ['secondary damage', 'dano secundario'],
        'vin' => ['vin', 'vin (status)', 'chassis no.', 'chassis no', 'chassis number', 'chassis', 'vin #'],
        'documento' => ['title code', 'doc type', 'title/sale doc', 'title state/type', 'sale document', 'title'],
        'ubicacion' => ['location', 'sale location', 'selling branch', 'yard', 'branch'],
        'precio' => ['price', 'fob price', 'total price', 'vehicle price', 'buy it now', 'buy now price'],
        'referencia' => ['lot number', 'lot #', 'lot', 'stock #', 'stock', 'ref no.', 'ref no', 'stock no.',
            'stock no', 'item #', 'item number', 'stock number'],
    ];

    private const TRADUCCIONES = [
        // transmisión
        'automatic' => 'Automática', 'auto' => 'Automática', 'at' => 'Automática', 'manual' => 'Manual',
        'automatic transmission' => 'Automática', 'manual transmission' => 'Manual', 'cvt transmission' => 'Automática CVT',
        'mt' => 'Manual', 'cvt' => 'Automática CVT', 'semi-automatic' => 'Semiautomática',
        // combustible
        'gas' => 'Bencina', 'gasoline' => 'Bencina', 'petrol' => 'Bencina', 'diesel' => 'Diésel',
        'hybrid' => 'Híbrido', 'hybrid engine' => 'Híbrido', 'electric' => 'Eléctrico',
        'flexible fuel' => 'Flex', 'flex fuel' => 'Flex', 'plug-in hybrid' => 'Híbrido enchufable',
        'lpg' => 'Gas licuado', 'cng' => 'GNC',
        // tracción
        'front-wheel drive' => 'Delantera (FWD)', 'front wheel drive' => 'Delantera (FWD)',
        'fwd' => 'Delantera (FWD)', '2wd' => '2WD',
        'rear-wheel drive' => 'Trasera (RWD)', 'rear wheel drive' => 'Trasera (RWD)', 'rwd' => 'Trasera (RWD)',
        'all wheel drive' => 'Integral (AWD)', 'all-wheel drive' => 'Integral (AWD)', 'awd' => 'Integral (AWD)',
        '4x4 w/front whl drv' => '4x4', '4x4 w/rear wheel drv' => '4x4', '4x4' => '4x4', '4wd' => '4x4',
        'four by four' => '4x4', '4x4 drive' => '4x4', '4wd drive' => '4x4', 'awd drive' => 'Integral (AWD)',
        'front wheel drive (fwd)' => 'Delantera (FWD)', 'rear wheel drive (rwd)' => 'Trasera (RWD)', 'all wheel drive (awd)' => 'Integral (AWD)',
        // colores
        'black' => 'Negro', 'white' => 'Blanco', 'silver' => 'Plateado', 'gray' => 'Gris', 'grey' => 'Gris',
        'red' => 'Rojo', 'blue' => 'Azul', 'green' => 'Verde', 'gold' => 'Dorado', 'beige' => 'Beige',
        'brown' => 'Café', 'orange' => 'Naranjo', 'yellow' => 'Amarillo', 'purple' => 'Morado',
        'maroon' => 'Burdeo', 'burgundy' => 'Burdeo', 'pearl' => 'Perla', 'charcoal' => 'Gris oscuro',
        'tan' => 'Beige', 'pink' => 'Rosado', 'turquoise' => 'Turquesa', 'teal' => 'Verde azulado',
        // daños
        'front end' => 'Parte delantera', 'rear end' => 'Parte trasera', 'side' => 'Lateral',
        'left side' => 'Lateral izquierdo', 'right side' => 'Lateral derecho',
        'left front' => 'Delantero izquierdo', 'right front' => 'Delantero derecho',
        'left rear' => 'Trasero izquierdo', 'right rear' => 'Trasero derecho',
        'all over' => 'Daño general', 'minor dent/scratches' => 'Abolladuras/rayones menores',
        'hail' => 'Granizo', 'water/flood' => 'Inundación', 'flood' => 'Inundación',
        'mechanical' => 'Mecánico', 'normal wear' => 'Desgaste normal', 'vandalism' => 'Vandalismo',
        'undercarriage' => 'Parte inferior', 'rollover' => 'Volcamiento', 'burn' => 'Quemado',
        'burn - engine' => 'Quemado (motor)', 'burn - interior' => 'Quemado (interior)',
        'top/roof' => 'Techo', 'roof' => 'Techo', 'frame damage' => 'Daño de chasis',
        'stripped' => 'Desmantelado', 'biohazard/chemical' => 'Riesgo biológico/químico',
        'rejected repair' => 'Reparación rechazada', 'replaced vin' => 'VIN reemplazado',
        'damage history' => 'Historial de daños', 'partial repair' => 'Reparación parcial',
        'missing/altered vin' => 'VIN faltante/alterado', 'electrical' => 'Eléctrico',
        'collision' => 'Colisión', 'theft' => 'Robo', 'none' => 'Ninguno', 'unknown' => 'Desconocido',
        'suspension' => 'Suspensión', 'cosmetic' => 'Estético', 'repossession' => 'Embargo',
        // estado
        'run and drive' => 'Arranca y anda', 'run & drive' => 'Arranca y anda', 'runs and drives' => 'Arranca y anda',
        'engine start program' => 'Motor enciende', 'starts' => 'Enciende', 'stationary' => 'Enciende (no se movió)',
        "won't start" => 'No arranca', 'does not start' => 'No arranca',
        // carrocería
        'sedan 4d' => 'Sedán 4 puertas', 'sedan 2d' => 'Sedán 2 puertas', 'sedan' => 'Sedán',
        '4dr spor' => 'SUV', 'sport utility' => 'SUV', 'suv' => 'SUV', 'suv 4d' => 'SUV',
        'hatchbac' => 'Hatchback', 'hatchback' => 'Hatchback', 'hatchback 4d' => 'Hatchback 5 puertas',
        'coupe' => 'Coupé', 'coupe 2d' => 'Coupé', 'convertible' => 'Convertible', 'conv' => 'Convertible',
        'wagon' => 'Station wagon', 'station wagon' => 'Station wagon', 'wagon 4d' => 'Station wagon',
        'pickup' => 'Camioneta (pickup)', 'crew pic' => 'Camioneta doble cabina', 'crew cab' => 'Camioneta doble cabina',
        'extended' => 'Camioneta cabina extendida', 'minivan' => 'Minivan', 'van' => 'Furgón', 'truck' => 'Camión',
        'mini vehicle' => 'Mini vehículo', 'motorcycle' => 'Motocicleta',
        // varios
        'yes' => 'Sí', 'no' => 'No', 'present' => 'Sí', 'missing' => 'No',
    ];

    private const SIN_TRADUCIR = ['vin', 'referencia', 'precio', 'ubicacion', 'documento'];

    /** @var array<string, string> etiqueta => campo */
    private array $etiquetaACampo = [];

    public function __construct()
    {
        foreach (self::SINONIMOS as $campo => $lista) {
            foreach ($lista as $s) {
                $this->etiquetaACampo[$s] = $campo;
            }
        }
    }

    public function campoDeEtiqueta(string $etiqueta): ?string
    {
        return $this->etiquetaACampo[$this->limpiarEtiqueta($etiqueta)] ?? null;
    }

    /** @param array<int, array{0: string, 1: string}> $pares */
    public function desdePares(array $pares): array
    {
        $specs = [];
        foreach ($pares as [$etiqueta, $valor]) {
            $campo = $this->campoDeEtiqueta($etiqueta);
            $valor = $this->limpiarValor($valor);
            if ($campo && $valor !== '' && !isset($specs[$campo]) && mb_strlen($valor) < 200) {
                $specs[$campo] = $valor;
            }
        }
        return $specs;
    }

    /** '2015 TOYOTA PRIUS S' -> año, marca, modelo, versión (como respaldo). */
    public function desdeTitulo(string $titulo): array
    {
        if (!preg_match('/\b((?:19|20)\d{2})\s+([A-Za-z][\w\-]*)\s+(.+)/u', $titulo, $m)) {
            return [];
        }
        $resto = trim((preg_split('/\s+for sale\b|\||\s-\s/i', $m[3]) ?: [''])[0]);
        $palabras = preg_split('/\s+/', $resto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $datos = ['anio' => $m[1], 'marca' => $m[2]];
        if ($palabras) {
            $datos['modelo'] = $palabras[0];
            if (count($palabras) > 1) {
                $datos['version'] = implode(' ', array_slice($palabras, 1, 5));
            }
        }
        return $datos;
    }

    /** @param array<string, string> $crudo */
    public function normalizar(array $crudo): array
    {
        $final = [];
        foreach ($crudo as $campo => $valor) {
            $v = $this->limpiarValor((string) $valor);
            if ($v === '' || in_array(mb_strtolower($v), ['-', 'n/a', 'na', 'null', 'none', '--', 'ask'], true)) {
                continue;
            }
            switch ($campo) {
                case 'kilometraje':
                    $v = $this->formatearKilometraje($v);
                    break;
                case 'motor':
                    $v = $this->formatearMotor($v);
                    break;
                case 'cilindros':
                    $v = preg_match('/(\d+)\s*cyl/i', $v, $m) ? $m[1] : $v;
                    break;
                case 'anio':
                    if (!preg_match('/(19|20)\d{2}/', $v, $m)) {
                        continue 2;
                    }
                    $v = $m[0];
                    break;
                case 'marca':
                case 'modelo':
                case 'version':
                    $v = $this->capitalizarNombre($v);
                    break;
                default:
                    if (!in_array($campo, self::SIN_TRADUCIR, true)) {
                        $v = $this->traducir($v);
                        if ($this->esMayusculas($v) && mb_strlen($v) > 3) {
                            $v = mb_strtoupper(mb_substr($v, 0, 1)) . mb_strtolower(mb_substr($v, 1));
                        }
                    }
            }
            $final[$campo] = $v;
        }
        return $final;
    }

    public function traducir(string $valor): string
    {
        $clave = mb_strtolower(trim($valor));
        if (isset(self::TRADUCCIONES[$clave])) {
            return self::TRADUCCIONES[$clave];
        }
        // "SILVER / BLACK", "FRONT END, SIDE"
        $partes = array_map('trim', preg_split('#[/,]#', $valor) ?: []);
        if (count($partes) > 1 && !array_filter($partes, fn($p) => !isset(self::TRADUCCIONES[mb_strtolower($p)]))) {
            $sep = str_contains($valor, '/') ? ' / ' : ', ';
            return implode($sep, array_map(fn($p) => self::TRADUCCIONES[mb_strtolower($p)], $partes));
        }
        return $valor;
    }

    public function formatearKilometraje(string $valor): string
    {
        $n = $this->numero(explode('(', $valor)[0]);
        if ($n === null) {
            return $valor;
        }
        $estado = '';
        if (preg_match('/\(([^)]+)\)|\b(actual|exempt|not actual|exceeds mechanical limits)\b/i', $valor, $m)) {
            $txt = mb_strtolower(trim(($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '')));
            $estado = [
                'actual' => ' (real)', 'not actual' => ' (no real)', 'exempt' => ' (exento)',
                'exceeds mechanical limits' => ' (excede límite mecánico)',
            ][$txt] ?? '';
        }
        if (preg_match('/\b(mi|miles|millas)\b/i', $valor)) {
            $km = (int) round($n * 1.609344);
            return $this->miles($km) . ' km (' . $this->miles($n) . ' millas)' . $estado;
        }
        return $this->miles($n) . ' km' . $estado;
    }

    public function formatearMotor(string $valor): string
    {
        if (preg_match('/^\s*([\d.,]+)\s*cc\s*$/i', $valor, $m)) {
            $n = $this->numero($m[1]);
            return $n ? $this->miles($n) . ' cc' : $valor;
        }
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*L\s+(\d+)\s*$/i', $valor, $m)) {
            return $m[1] . 'L ' . $m[2] . ' cilindros';
        }
        return $valor;
    }

    // ------------------------------------------------------------------ privados

    private function limpiarEtiqueta(string $e): string
    {
        $e = $this->sinAcentos(mb_strtolower($e));
        $e = (string) preg_replace('/\s+/u', ' ', $e);
        return trim($e, " :.-*\t\n\r");
    }

    private function limpiarValor(string $v): string
    {
        $t = trim((string) preg_replace('/\s+/u', ' ', $v), " :|\t\n\r");
        // "Present Present" (el sitio repite el dato para móvil y escritorio)
        if (preg_match('/^(.+) \1$/u', $t, $m)) {
            return $m[1];
        }
        return $t;
    }

    private function sinAcentos(string $t): string
    {
        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);
    }

    /** 'MAZDA CX-5 GRAND TOURING' -> 'Mazda CX-5 Grand Touring' (respeta siglas y códigos). */
    private function capitalizarNombre(string $v): string
    {
        if (!$this->esMayusculas($v)) {
            return $v;
        }
        $palabras = preg_split('/\s+/', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(' ', array_map(
            fn($p) => ctype_alpha($p) && strlen($p) > 3 ? ucfirst(strtolower($p)) : $p,
            $palabras
        ));
    }

    private function esMayusculas(string $v): bool
    {
        return mb_strtoupper($v) === $v && mb_strtolower($v) !== $v;
    }

    private function numero(string $t): ?int
    {
        $d = preg_replace('/\D/', '', $t);
        return $d === '' ? null : (int) $d;
    }

    private function miles(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
