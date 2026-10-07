<?php
declare(strict_types=1);

namespace App\Servicios;

use RuntimeException;

/**
 * Descargas con cURL. Solo permite http/https hacia direcciones públicas
 * (para que nadie use el servidor para entrar a redes internas) y limita el
 * tamaño de cada respuesta.
 */
final class ClienteHttp
{
    private const AGENTE = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    public function __construct(
        private bool $permitirRedLocal = false,
        private int $maxBytes = 20 * 1024 * 1024,
    ) {
    }

    public function obtener(string $url, array $cabeceras = [], int $timeout = 30): RespuestaHttp
    {
        for ($saltos = 0; $saltos <= 5; $saltos++) {
            [$host, $ip, $puerto] = $this->validar($url);
            $ch = curl_init($url);
            $max = $this->maxBytes;
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => self::AGENTE,
                CURLOPT_HTTPHEADER => array_merge([
                    'Accept: text/html,application/xhtml+xml,application/json,image/*,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9,es;q=0.8',
                ], $cabeceras),
                // Fija la IP ya validada (evita que el DNS cambie entre validar y conectar)
                CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $host, $puerto, str_contains($ip, ':') ? "[$ip]" : $ip)],
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => static fn($c, $total, $bajado) => $bajado > $max ? 1 : 0,
            ]);
            $cuerpo = curl_exec($ch);
            $codigo = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $tipo = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
            $redireccion = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $error = curl_error($ch);
            curl_close($ch);

            if ($cuerpo === false) {
                throw new RuntimeException('No se pudo descargar ' . $host . ': ' . $error);
            }
            if (in_array($codigo, [301, 302, 303, 307, 308], true) && $redireccion !== '') {
                $url = Url::absoluta($url, $redireccion);
                continue;
            }
            return new RespuestaHttp($codigo, $tipo, (string) $cuerpo, $url);
        }
        throw new RuntimeException('Demasiadas redirecciones.');
    }

    /** @return array{0: string, 1: string, 2: int} host, ip, puerto */
    private function validar(string $url): array
    {
        $p = parse_url($url);
        $esquema = strtolower($p['scheme'] ?? '');
        $host = strtolower($p['host'] ?? '');
        if (!in_array($esquema, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Link inválido: debe empezar con http:// o https://');
        }
        $puerto = (int) ($p['port'] ?? ($esquema === 'https' ? 443 : 80));
        $hostSinCorchetes = trim($host, '[]');

        $ips = filter_var($hostSinCorchetes, FILTER_VALIDATE_IP)
            ? [$hostSinCorchetes]
            : array_merge(
                gethostbynamel($hostSinCorchetes) ?: [],
                array_column(@dns_get_record($hostSinCorchetes, DNS_AAAA) ?: [], 'ipv6')
            );
        if (!$ips) {
            throw new RuntimeException('No se encontró el sitio: ' . $host);
        }
        foreach ($ips as $ip) {
            $publica = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if (!$publica && !$this->permitirRedLocal) {
                throw new RuntimeException('Dirección no permitida: ' . $host);
            }
        }
        return [$hostSinCorchetes, $ips[0], $puerto];
    }
}
