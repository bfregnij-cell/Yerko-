<?php
declare(strict_types=1);

/*
 * Pruebas unitarias sin dependencias. Ejecutar:  php tests/unidad.php
 */

spl_autoload_register(function (string $clase): void {
    $ruta = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
    if (str_starts_with($clase, 'App\\') && is_file($ruta)) {
        require $ruta;
    }
});

use App\Extractores\ExtractorFactory;
use App\Modelos\Captura;
use App\Servicios\GeneradorPublicacion;
use App\Servicios\LectorHtml;
use App\Servicios\Marcador;
use App\Servicios\NormalizadorSpecs;
use App\Servicios\Url;

$fallas = 0;
$total = 0;

function prueba(string $nombre, callable $fn): void
{
    global $fallas, $total;
    $total++;
    try {
        $fn();
        echo "✔ $nombre\n";
    } catch (Throwable $e) {
        $fallas++;
        echo "✘ $nombre\n    " . $e->getMessage() . "\n";
    }
}

function igual(mixed $esperado, mixed $obtenido, string $msg = ''): void
{
    if ($esperado !== $obtenido) {
        throw new Exception(($msg ? "$msg: " : '') . 'esperado ' . var_export($esperado, true)
            . ', obtenido ' . var_export($obtenido, true));
    }
}

function contiene(string $aguja, string $pajar): void
{
    if (!str_contains($pajar, $aguja)) {
        throw new Exception("no contiene «{$aguja}» en:\n{$pajar}");
    }
}

$n = new NormalizadorSpecs();

prueba('detecta el sitio por el dominio', function () {
    igual('beforward', ExtractorFactory::paraUrl('https://www.beforward.jp/toyota/prius/bf123/id/456/')->clave());
    igual('copart', ExtractorFactory::paraUrl('https://www.copart.com/lot/12345678/clean-title-2018-toyota-camry')->clave());
    igual('iaai', ExtractorFactory::paraUrl('https://www.iaai.com/VehicleDetail/41234567~US')->clave());
    igual('generico', ExtractorFactory::paraUrl('https://ejemplo.cl/auto/1')->clave());
});

prueba('kilometraje en millas y km', function () use ($n) {
    igual('198.683 km (123.456 millas) (real)', $n->formatearKilometraje('123,456 mi (ACTUAL)'));
    igual('85.000 km', $n->formatearKilometraje('85,000 km'));
    igual('45.000 km', $n->formatearKilometraje('45000'));
});

prueba('traducciones', function () use ($n) {
    igual('Automática', $n->traducir('AUTOMATIC'));
    igual('Delantera (FWD)', $n->traducir('Front-wheel Drive'));
    igual('Plateado / Negro', $n->traducir('SILVER / BLACK'));
    igual('Algo raro', $n->traducir('Algo raro'));
});

prueba('tabla estilo BE FORWARD', function () use ($n) {
    $d = $n->normalizar($n->desdePares([
        ['Ref No.', 'BF123456'], ['Mileage', '85,000 km'], ['Registration Year/month', '2015/3'],
        ['Engine', '1,790cc'], ['Transmission', 'AT'], ['Fuel', 'Hybrid'], ['Drive', '2WD'],
        ['Ext. Color', 'Pearl'], ['Doors', '5'], ['Chassis No.', 'ZVW30-1234***'], ['Steering', 'Right'],
    ]));
    igual('2015', $d['anio']);
    igual('85.000 km', $d['kilometraje']);
    igual('1.790 cc', $d['motor']);
    igual('Automática', $d['transmision']);
    igual('Híbrido', $d['combustible']);
    igual('Perla', $d['color']);
    igual('BF123456', $d['referencia']);
    igual('ZVW30-1234***', $d['vin']);
});

prueba('Copart: datos de la API y fotos en alta resolución', function () use ($n) {
    $api = ['data' => ['lotDetails' => [
        'ln' => 12345678, 'mkn' => 'TOYOTA', 'lm' => 'CAMRY', 'lcy' => 2018, 'orr' => 54321, 'egn' => '2.5L 4',
        'tmtp' => 'AUTOMATIC', 'drv' => 'Front-wheel Drive', 'ft' => 'GAS', 'clr' => 'SILVER', 'bstl' => 'SEDAN 4D',
        'dd' => 'FRONT END', 'sdd' => 'MINOR DENT/SCRATCHES', 'hk' => 'YES', 'lcd' => 'RUN AND DRIVE',
    ]]];
    $fotos = ['data' => ['imagesList' => [
        'FULL_IMAGE' => [['url' => 'https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/a_ful.jpg']],
        'HIGH_RESOLUTION_IMAGE' => [
            ['url' => 'https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/a_hrs.jpg'],
            ['url' => 'https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/b_hrs.jpg'],
        ],
    ]]];
    $c = new Captura(url: 'https://www.copart.com/lot/12345678', titulo: '2018 TOYOTA CAMRY L for Sale', jsons: [$api, $fotos]);
    $e = ExtractorFactory::paraUrl($c->url);
    $d = $e->especificaciones($c, $n);
    igual('Toyota Camry 2018', $d['titulo']);
    igual('87.421 km (54.321 millas)', $d['kilometraje']);
    igual('2.5L 4 cilindros', $d['motor']);
    igual('Bencina', $d['combustible']);
    igual('Sedán 4 puertas', $d['carroceria']);
    igual('Parte delantera', $d['danio_principal']);
    igual('Abolladuras/rayones menores', $d['danio_secundario']);
    igual('Arranca y anda', $d['estado']);
    igual('Sí', $d['llaves']);
    igual([
        'https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/a_hrs.jpg',
        'https://cs.copart.com/v1/AUTH_svc.pdoc00001/lpp/0123/b_hrs.jpg',
    ], array_column($e->candidatas($c), 0));
    igual('https://cs.copart.com/x/abc_hrs.jpg', $e->altaResolucion('https://cs.copart.com/x/abc_thb.jpg'));
});

prueba('IAAI: claves de imagen y pares de la página', function () use ($n) {
    $vm = ['inventoryView' => ['imageDimensions' => ['keys' => ['$values' => [
        ['K' => '36123456~SID~B123~I1~RW2576~H1932~TH0', 'AR' => 1.33],
        ['K' => '36123456~SID~B123~I2~RW2576~H1932~TH0', 'AR' => 1.33],
    ]]]]];
    $c = new Captura(
        url: 'https://www.iaai.com/VehicleDetail/41234567~US',
        h1: '2020 HONDA CIVIC EX',
        pares: [['Odometer:', '45,210 mi (Actual)'], ['Primary Damage:', 'Rear End'],
            ['Start Code:', 'Run & Drive'], ['Key:', 'Present'], ['Fuel Type:', 'Gasoline']],
        jsons: [$vm],
    );
    $e = ExtractorFactory::paraUrl($c->url);
    $d = $e->especificaciones($c, $n);
    igual('Honda Civic 2020', $d['titulo']);
    igual('EX', $d['version']);
    igual('Parte trasera', $d['danio_principal']);
    igual('Arranca y anda', $d['estado']);
    igual('Sí', $d['llaves']);
    $fotos = $e->candidatas($c);
    igual(2, count($fotos));
    contiene('imageKeys=36123456', $fotos[0][0]);
    contiene('width=1920', $fotos[0][0]);
});

prueba('fotos de la página: filtra logos, otros dominios y otros autos', function () {
    $c = new Captura(url: 'https://www.beforward.jp/x', imagenes: [
        'https://image-cdn.beforward.jp/large/202401/1234/a.jpg',
        'https://image-cdn.beforward.jp/large/202401/1234/b.jpg',
        'https://image-cdn.beforward.jp/large/202401/1234/c.jpg',
        'https://image-cdn.beforward.jp/small/202401/9999/otro_auto.jpg',
        'https://www.beforward.jp/img/logo.png',
        'https://www.facebook.com/tr.jpg',
    ]);
    igual([
        'https://image-cdn.beforward.jp/large/202401/1234/a.jpg',
        'https://image-cdn.beforward.jp/large/202401/1234/b.jpg',
        'https://image-cdn.beforward.jp/large/202401/1234/c.jpg',
    ], array_column(ExtractorFactory::paraUrl($c->url)->candidatas($c), 0));
});

prueba('nombres: respeta siglas y códigos de modelo', function () use ($n) {
    $d = $n->normalizar(['marca' => 'MAZDA', 'modelo' => 'CX-5', 'version' => 'GRAND TOURING', 'carroceria' => 'SUV']);
    igual('Mazda', $d['marca']);
    igual('CX-5', $d['modelo']);
    igual('Grand Touring', $d['version']);
    igual('SUV', $d['carroceria']);
});

prueba('plantilla omite líneas sin dato', function () {
    $t = (new GeneradorPublicacion())->generar(['titulo' => 'Toyota Prius 2015', 'anio' => '2015', 'motor' => '1.790 cc']);
    contiene('🚗 Toyota Prius 2015', $t);
    contiene('⚙️ Motor: 1.790 cc', $t);
    if (str_contains($t, 'Kilometraje') || str_contains($t, '{')) {
        throw new Exception("quedaron líneas sin dato:\n$t");
    }
});

prueba('lector HTML: tablas de 4 columnas, "Etiqueta:", imágenes y JSON', function () {
    $html = <<<HTML
<html><head><title>2019 MAZDA CX-5 | Autos</title><meta property="og:image" content="/f/01.jpg">
<script type="application/json">{"K":"abc~1"}</script></head><body>
<h1>2019 MAZDA CX-5 GRAND TOURING</h1>
<img src="/f/01.jpg"><img data-src="f/02.jpg"><img srcset="/f/03_s.jpg 300w, /f/03.jpg 1200w">
<table><tr><th>Mileage</th><td>32,000 km</td><th>Transmission</th><td>Automatic</td></tr></table>
<ul><li><span>Engine:</span><span>2.5L 4</span></li><li>Drive: AWD</li></ul>
</body></html>
HTML;
    $c = (new LectorHtml())->leer($html, 'https://autos.ejemplo.com/auto/77');
    igual('2019 MAZDA CX-5 GRAND TOURING', $c->h1);
    $pares = array_map(fn($p) => $p[0] . '=' . $p[1], $c->pares);
    foreach (['Mileage=32,000 km', 'Transmission=Automatic', 'Engine:=2.5L 4', 'Drive:=AWD'] as $esperado) {
        if (!in_array($esperado, $pares, true)) {
            throw new Exception("falta par $esperado en " . implode(' | ', $pares));
        }
    }
    foreach (['https://autos.ejemplo.com/f/01.jpg', 'https://autos.ejemplo.com/auto/f/02.jpg', 'https://autos.ejemplo.com/f/03.jpg'] as $u) {
        if (!in_array($u, $c->imagenes, true)) {
            throw new Exception("falta imagen $u en " . implode(' | ', $c->imagenes));
        }
    }
    igual([['K' => 'abc~1']], $c->jsons);
});

prueba('detección de páginas anti-robots', function () {
    $l = new LectorHtml();
    igual(true, $l->esBloqueo('<html><title>Pardon Our Interruption</title></html>'));
    igual(false, $l->esBloqueo('<html><title>2018 Toyota Camry</title></html>'));
});

prueba('URLs relativas', function () {
    igual('https://a.com/x/y.jpg', Url::absoluta('https://a.com/p/q', '/x/y.jpg'));
    igual('https://a.com/p/y.jpg', Url::absoluta('https://a.com/p/q', 'y.jpg'));
    igual('https://a.com/y.jpg', Url::absoluta('https://a.com/p/q', '../y.jpg'));
    igual('https://cdn.b.com/z.jpg', Url::absoluta('https://a.com/p/q', '//cdn.b.com/z.jpg'));
    igual('', Url::absoluta('https://a.com/', 'data:image/png;base64,xx'));
    igual('https://beforward.jp/a', Url::normalizarEntrada(' beforward.jp/a '));
});

prueba('captura desde el botón: descarta datos con formato incorrecto', function () {
    $c = Captura::desdeMarcador([
        'url' => 'https://www.iaai.com/VehicleDetail/1~US', 'h1' => ['no es texto'],
        'pares' => [['Odometer:', '1 mi'], ['solo uno'], [1, 2]],
        'imagenes' => ['https://vis.iaai.com/a.jpg', 'javascript:alert(1)', 'https://vis.iaai.com/a.jpg'],
        'jsons' => ['{"K":"a~b"}', 'no es json'],
    ]);
    igual('', $c->h1);
    igual([['Odometer:', '1 mi']], $c->pares);
    igual(['https://vis.iaai.com/a.jpg'], $c->imagenes);
    igual([['K' => 'a~b']], $c->jsons);
});

prueba('el botón se genera con el destino y el token', function () {
    $m = new Marcador(dirname(__DIR__) . '/assets/marcador.js');
    $codigo = $m->codigo('https://mi-sitio.cl/extractor/index.php', 'tok123');
    contiene("var DESTINO = 'https://mi-sitio.cl/extractor/index.php', TOKEN = 'tok123';", $codigo);
    if (str_contains($codigo, '/*')) {
        throw new Exception('quedaron comentarios en el código del botón');
    }
    contiene('javascript:', $m->enlace('https://x.cl/index.php', ''));
});

prueba('valores reales vistos en IAAI y BE FORWARD', function () use ($n) {
    igual(
        ['transmision' => 'Automática', 'traccion' => '4x4', 'cilindros' => '6', 'llaves' => 'Sí', 'estado' => 'Arranca y anda'],
        $n->normalizar(['transmision' => 'Automatic Transmission', 'traccion' => '4X4 Drive', 'cilindros' => '6 Cylinders',
            'llaves' => 'Present Present', 'estado' => 'Run & Drive Run & Drive'])
    );
    $c = new Captura(
        url: 'https://www.beforward.jp/mazda/cx-5/ce451821/id/16337108/',
        titulo: 'Used 2019 MAZDA CX-5 XD PROACTIVE/3DA-KF2P for Sale CE451821 - BE FORWARD',
        h1: '2019 MAZDA',
        pares: [['Version/Class', 'XD PROACTIVE'], ['Mileage', '68,803 km']],
    );
    $d = ExtractorFactory::paraUrl($c->url)->especificaciones($c, $n);
    igual('Mazda CX-5 2019', $d['titulo']);
    igual('XD Proactive', $d['version']);
});

prueba('el servidor no descarga desde redes internas', function () {
    foreach (['http://127.0.0.1/', 'http://localhost:8080/', 'http://192.168.1.1/', 'http://169.254.169.254/latest/meta-data/', 'file:///etc/passwd'] as $u) {
        try {
            (new App\Servicios\ClienteHttp(false))->obtener($u);
            throw new Exception("permitió $u");
        } catch (RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'permitió')) {
                throw $e;
            }
        }
    }
});

echo "\n" . ($total - $fallas) . " de $total pruebas pasaron\n";
exit($fallas ? 1 : 0);
