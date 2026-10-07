<?php
declare(strict_types=1);

namespace App\Extractores;

/**
 * Detecta el sitio por el dominio del link y entrega su extractor.
 */
final class ExtractorFactory
{
    public static function paraUrl(string $url): Extractor
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ([new BeForwardExtractor(), new CopartExtractor(), new IaaiExtractor()] as $extractor) {
            if ($host !== '' && $extractor->coincide($host)) {
                return $extractor;
            }
        }
        return new GenericoExtractor();
    }
}
