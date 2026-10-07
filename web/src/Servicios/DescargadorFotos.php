<?php
declare(strict_types=1);

namespace App\Servicios;

use Throwable;

/**
 * Descarga las fotos candidatas, descarta miniaturas, logos y repetidas, y
 * las guarda como foto_01.jpg, foto_02.jpg, ...
 */
final class DescargadorFotos
{
    private const ANCHO_MINIMO = 480;
    private const ALTO_MINIMO = 320;
    private const EXTENSIONES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];

    public function __construct(private ClienteHttp $http, private int $maximo = 60)
    {
    }

    /**
     * @param array<int, string[]> $candidatas Por foto: [url_alta_res, url_original]
     * @return array{0: string[], 1: string[]} [archivos guardados, URLs que no se pudieron descargar]
     */
    public function descargar(array $candidatas, string $carpeta, string $referer): array
    {
        $guardadas = [];
        $fallidas = [];
        $hashes = [];
        foreach ($candidatas as $opciones) {
            if (count($guardadas) >= $this->maximo) {
                break;
            }
            $descargada = false;
            foreach ($opciones as $url) {
                $datos = $this->bajar($url, $referer);
                if ($datos === null) {
                    continue;
                }
                $descargada = true;
                $info = @getimagesizefromstring($datos);
                if (!$info || !isset(self::EXTENSIONES[$info[2]])) {
                    continue;
                }
                if ($info[0] < self::ANCHO_MINIMO || $info[1] < self::ALTO_MINIMO) {
                    break; // miniatura, logo, ícono…
                }
                $hash = md5($datos);
                if (isset($hashes[$hash])) {
                    break;
                }
                $hashes[$hash] = true;
                $nombre = sprintf('foto_%02d.%s', count($guardadas) + 1, self::EXTENSIONES[$info[2]]);
                file_put_contents($carpeta . '/' . $nombre, $datos);
                $guardadas[] = $nombre;
                break;
            }
            if (!$descargada) {
                $fallidas[] = $opciones[count($opciones) - 1];
            }
        }
        return [$guardadas, $fallidas];
    }

    private function bajar(string $url, string $referer): ?string
    {
        try {
            $r = $this->http->obtener($url, ['Referer: ' . $referer], 25);
        } catch (Throwable) {
            return null;
        }
        return $r->ok() && $r->cuerpo !== '' ? $r->cuerpo : null;
    }
}
