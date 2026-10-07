<?php
declare(strict_types=1);

namespace App\Modelos;

/**
 * Resultado de una extracción: datos técnicos, fotos y texto para publicar.
 */
final class Vehiculo
{
    /**
     * @param array<string, string> $datos         Campo => valor ya normalizado
     * @param string[]              $fotos         Archivos guardados (foto_01.jpg, ...)
     * @param string[]              $fotosRemotas  URLs que no se pudieron descargar desde el servidor
     * @param array<int, array{0: string, 1: string}> $pares Pares originales de la página (para revisión)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $sitio,
        public readonly string $url,
        public array $datos = [],
        public string $texto = '',
        public array $fotos = [],
        public array $fotosRemotas = [],
        public array $pares = [],
        public int $creado = 0,
    ) {
        $this->creado = $this->creado ?: time();
    }

    public static function nuevoId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public static function idValido(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{16}$/', $id);
    }

    public function titulo(): string
    {
        return $this->datos['titulo'] ?? 'Auto';
    }

    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'sitio' => $this->sitio,
            'url' => $this->url,
            'datos' => $this->datos,
            'texto' => $this->texto,
            'fotos' => $this->fotos,
            'fotos_remotas' => $this->fotosRemotas,
            'pares' => $this->pares,
            'creado' => $this->creado,
        ];
    }

    public static function desdeArreglo(array $a): self
    {
        return new self(
            id: (string) $a['id'],
            sitio: (string) $a['sitio'],
            url: (string) $a['url'],
            datos: (array) $a['datos'],
            texto: (string) $a['texto'],
            fotos: (array) $a['fotos'],
            fotosRemotas: (array) ($a['fotos_remotas'] ?? []),
            pares: (array) ($a['pares'] ?? []),
            creado: (int) ($a['creado'] ?? 0),
        );
    }
}
