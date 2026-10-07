<?php
declare(strict_types=1);

namespace App\Servicios;

use App\Extractores\ExtractorFactory;
use App\Modelos\Captura;
use App\Modelos\RepositorioVehiculos;
use App\Modelos\Vehiculo;

/**
 * Proceso completo: captura de la página -> especificaciones + fotos + texto.
 */
final class ServicioExtraccion
{
    public function __construct(
        private ClienteHttp $http,
        private RepositorioVehiculos $repositorio,
        private NormalizadorSpecs $normalizador,
        private GeneradorPublicacion $generador,
        private int $maxFotos = 60,
    ) {
    }

    /** El servidor descarga la página por su cuenta (funciona con sitios sin anti-robots). */
    public function desdeLink(string $url): Vehiculo
    {
        $url = Url::normalizarEntrada($url);
        $respuesta = $this->http->obtener($url);
        $lector = new LectorHtml();
        if (!$respuesta->ok() || $lector->esBloqueo($respuesta->cuerpo)) {
            throw new SitioBloqueadoException(sprintf(
                '%s no permitió que el servidor leyera la página (código %d).',
                ExtractorFactory::paraUrl($url)->nombre(),
                $respuesta->codigo
            ));
        }
        return $this->procesar($lector->leer($respuesta->cuerpo, $respuesta->urlFinal));
    }

    /** Procesa una página ya capturada (por el servidor o por el botón del navegador). */
    public function procesar(Captura $captura): Vehiculo
    {
        @set_time_limit(300);
        $extractor = ExtractorFactory::paraUrl($captura->url);
        $vehiculo = new Vehiculo(
            id: Vehiculo::nuevoId(),
            sitio: $extractor->clave(),
            url: $captura->url,
            pares: array_slice($captura->pares, 0, 300),
        );
        $vehiculo->datos = $extractor->especificaciones($captura, $this->normalizador);
        $vehiculo->texto = $this->generador->generar($vehiculo->datos);

        $descargador = new DescargadorFotos($this->http, $this->maxFotos);
        [$vehiculo->fotos, $vehiculo->fotosRemotas] = $descargador->descargar(
            $extractor->candidatas($captura),
            $this->repositorio->carpeta($vehiculo->id),
            $captura->url
        );

        $this->repositorio->guardar($vehiculo);
        return $vehiculo;
    }
}
