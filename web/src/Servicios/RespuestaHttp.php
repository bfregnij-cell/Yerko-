<?php
declare(strict_types=1);

namespace App\Servicios;

final class RespuestaHttp
{
    public function __construct(
        public readonly int $codigo,
        public readonly string $tipo,
        public readonly string $cuerpo,
        public readonly string $urlFinal,
    ) {
    }

    public function ok(): bool
    {
        return $this->codigo >= 200 && $this->codigo < 300;
    }
}
