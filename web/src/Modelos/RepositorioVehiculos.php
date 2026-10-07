<?php
declare(strict_types=1);

namespace App\Modelos;

use RuntimeException;
use ZipArchive;

/**
 * Guarda y recupera los resultados en disco (sin base de datos):
 *   almacen/resultados/<id>/vehiculo.json, publicacion.txt, foto_01.jpg, ...
 */
final class RepositorioVehiculos
{
    private string $base;

    public function __construct(string $almacen)
    {
        $this->base = rtrim($almacen, '/\\') . '/resultados';
        if (!is_dir($this->base) && !mkdir($this->base, 0775, true) && !is_dir($this->base)) {
            throw new RuntimeException('No se pudo crear la carpeta de resultados: ' . $this->base);
        }
    }

    public function carpeta(string $id): string
    {
        if (!Vehiculo::idValido($id)) {
            throw new RuntimeException('Identificador inválido.');
        }
        $dir = $this->base . '/' . $id;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public function guardar(Vehiculo $v): void
    {
        $dir = $this->carpeta($v->id);
        file_put_contents($dir . '/vehiculo.json', json_encode($v->aArreglo(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents($dir . '/publicacion.txt', $v->texto);
    }

    public function buscar(string $id): ?Vehiculo
    {
        if (!Vehiculo::idValido($id)) {
            return null;
        }
        $archivo = $this->base . '/' . $id . '/vehiculo.json';
        if (!is_file($archivo)) {
            return null;
        }
        $datos = json_decode((string) file_get_contents($archivo), true);
        return is_array($datos) ? Vehiculo::desdeArreglo($datos) : null;
    }

    /** Ruta de una foto del vehículo, o null si el nombre no corresponde. */
    public function rutaFoto(Vehiculo $v, string $nombre): ?string
    {
        if (!in_array($nombre, $v->fotos, true) || !preg_match('/^foto_\d{2}\.(jpg|png|webp|gif)$/', $nombre)) {
            return null;
        }
        $ruta = $this->base . '/' . $v->id . '/' . $nombre;
        return is_file($ruta) ? $ruta : null;
    }

    public function crearZip(Vehiculo $v): string
    {
        $dir = $this->carpeta($v->id);
        $zipRuta = $dir . '/fotos.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipRuta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP.');
        }
        foreach ($v->fotos as $f) {
            if ($ruta = $this->rutaFoto($v, $f)) {
                $zip->addFile($ruta, $f);
            }
        }
        $zip->addFromString('publicacion.txt', $v->texto);
        $zip->close();
        return $zipRuta;
    }

    // ------------------------------------------------------------------ trabajos pendientes

    /**
     * Guarda un trabajo por procesar (un link o los datos del botón) y devuelve su id.
     * Así la petición inicial responde al instante y el trabajo pesado se hace después.
     */
    public function guardarPendiente(array $trabajo): string
    {
        $id = Vehiculo::nuevoId();
        $dir = dirname($this->base) . '/pendientes';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $id . '.json', json_encode($trabajo, JSON_UNESCAPED_UNICODE));
        return $id;
    }

    /** Devuelve y elimina un trabajo pendiente (cada trabajo se ejecuta una sola vez). */
    public function tomarPendiente(string $id): ?array
    {
        if (!Vehiculo::idValido($id)) {
            return null;
        }
        $archivo = dirname($this->base) . '/pendientes/' . $id . '.json';
        $tomado = $archivo . '.tomado';
        if (!@rename($archivo, $tomado)) {
            return null;
        }
        $datos = json_decode((string) file_get_contents($tomado), true);
        unlink($tomado);
        return is_array($datos) ? $datos : null;
    }

    /** Borra resultados y trabajos con más de $dias días. */
    public function limpiarAntiguos(int $dias): void
    {
        $limite = time() - $dias * 86400;
        foreach (glob($this->base . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (filemtime($dir) < $limite && Vehiculo::idValido(basename($dir))) {
                array_map('unlink', glob($dir . '/*') ?: []);
                @rmdir($dir);
            }
        }
        foreach (glob(dirname($this->base) . '/pendientes/*.json') ?: [] as $archivo) {
            if (filemtime($archivo) < time() - 86400) {
                @unlink($archivo);
            }
        }
    }
}
