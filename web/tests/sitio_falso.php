<?php
/*
 * Sitio de autos falso para las pruebas de punta a punta.
 *   php -S 127.0.0.1:8701 tests/sitio_falso.php
 */
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$imagenes = [
    '/fotos/auto/01.jpg' => [1024, 768, [200, 30, 30]],
    '/fotos/auto/02.jpg' => [1024, 768, [30, 30, 200]],
    '/fotos/auto/03.jpg' => [1200, 900, [30, 160, 60]],
    '/fotos/auto/03_chica.jpg' => [300, 225, [30, 160, 60]],
    '/img/logo.jpg' => [120, 40, [0, 0, 0]],
];

if (isset($imagenes[$ruta])) {
    [$w, $h, $rgb] = $imagenes[$ruta];
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb));
    header('Content-Type: image/jpeg');
    imagejpeg($im);
    return;
}

if ($ruta === '/bloqueado') {
    http_response_code(403);
    echo '<html><head><title>Pardon Our Interruption</title></head><body>...</body></html>';
    return;
}

if ($ruta === '/auto') {
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
<!doctype html><html><head><meta charset="utf-8"><title>2019 MAZDA CX-5 GRAND TOURING | Autos</title>
<meta property="og:image" content="/fotos/auto/01.jpg"></head><body>
<img src="/img/logo.jpg" alt="logo">
<h1>2019 MAZDA</h1>
<table><tr><td>Mileage</td><td>Year</td><td>Engine</td><td>Trans.</td></tr>
  <tr><td> 32,000 km </td><td> 2019/2 </td><td>2.5L 4</td><td> AT </td></tr></table>
<div id="galeria">
  <img src="/fotos/auto/01.jpg">
  <img data-src="/fotos/auto/02.jpg" class="lazy">
  <img srcset="/fotos/auto/03_chica.jpg 300w, /fotos/auto/03.jpg 1200w">
</div>
<table>
  <tr><th>Mileage</th><td>32,000 km</td><th>Transmission</th><td>Automatic</td></tr>
  <tr><th>Fuel</th><td>Gasoline</td><th>Exterior Color</th><td>Red</td></tr>
</table>
<ul><li><span>Engine:</span><span>2.5L 4</span></li><li>Drive: AWD</li></ul>
<script>document.querySelectorAll('img.lazy').forEach(function (i) { i.src = i.dataset.src; });</script>
</body></html>
HTML;
    return;
}

http_response_code(404);
